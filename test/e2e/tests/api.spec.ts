import { test, expect, request as playwrightRequest } from '@playwright/test';

// Global test state — populated sequentially by each test
let firebaseIdToken: string;
let appId: string;
let clientId: string;
let refreshToken: string;
let accessToken: string;
let offerSdpId: string;

const FIREBASE_EMULATOR = `http://${process.env.FIREBASE_EMULATOR_HOST || 'test-firebase:9099'}`;
const EMAIL = `e2e-test-${Date.now()}@example.com`;
const PASSWORD = 'Test1234!';

async function getApiRequest() {
  return playwrightRequest.newContext({
    baseURL: process.env.API_BASE_URL || 'http://e2e-backend',
    extraHTTPHeaders: { 'Content-Type': 'application/json' },
  });
}

test.describe('API E2E', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(async () => {
    const maxRetries = 30;
    for (let i = 0; i < maxRetries; i++) {
      try {
        const req = await playwrightRequest.newContext({
          baseURL: process.env.API_BASE_URL || 'http://e2e-backend',
        });
        const resp = await req.get('/');
        if (resp.ok()) return;
      } catch (_) {
        // ignore network errors during startup
      }
      await new Promise(r => setTimeout(r, 2000));
    }
    throw new Error('Backend not ready after retries');
  });

  // -----------------------------------------------------------------------

  test('GET / returns API info', async () => {
    const req = await getApiRequest();
    const response = await req.get('/');
    expect(response.status()).toBe(200);
    const body = await response.json();
    // API returns { server_name, version }
    expect(body).toHaveProperty('server_name');
    expect(body).toHaveProperty('version');
  });

  test('Firebase: sign up test user with admin role', async () => {
    const firebaseReq = await playwrightRequest.newContext({
      baseURL: FIREBASE_EMULATOR,
      extraHTTPHeaders: { 'Content-Type': 'application/json' },
    });

    // Step 1: Create user
    const signUpResp = await firebaseReq.post(
      '/identitytoolkit.googleapis.com/v1/accounts:signUp?key=apikey',
      { data: { email: EMAIL, password: PASSWORD, returnSecureToken: true } }
    );
    expect(signUpResp.status()).toBe(200);
    const signUpBody = await signUpResp.json();
    const uid = signUpBody.localId as string;
    expect(uid).toBeTruthy();

    // Step 2: Set admin custom claims via emulator admin bypass ("Bearer owner")
    const claimsResp = await firebaseReq.post(
      '/identitytoolkit.googleapis.com/v1/accounts:update',
      {
        headers: { Authorization: 'Bearer owner' },
        data: { localId: uid, customAttributes: JSON.stringify({ role: 'admin' }) },
      }
    );
    expect(claimsResp.status()).toBe(200);

    // Step 3: Sign in again — fresh token now includes the custom claims
    const signInResp = await firebaseReq.post(
      '/identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=apikey',
      { data: { email: EMAIL, password: PASSWORD, returnSecureToken: true } }
    );
    expect(signInResp.status()).toBe(200);
    const signInBody = await signInResp.json();
    expect(signInBody.idToken).toBeTruthy();
    firebaseIdToken = signInBody.idToken as string;
  });

  test('POST /apps creates application', async () => {
    const req = await getApiRequest();
    const response = await req.post('/apps', {
      headers: {
        Authorization: `Bearer ${firebaseIdToken}`,
        'Content-Type': 'application/json',
      },
      data: {
        name: 'E2E Test App',
        description: 'Created by E2E test suite',
        owner: 'e2e-test',
      },
    });
    expect(response.status()).toBe(201);
    const body = await response.json();
    // ApplicationInfo: { app_id, name, description, owner, created_at }
    expect(body).toHaveProperty('app_id');
    expect(body).toHaveProperty('name');
    expect(body.name).toBe('E2E Test App');
    appId = body.app_id as string;
  });

  test('GET /apps/{appId} returns application', async () => {
    const req = await getApiRequest();
    const response = await req.get(`/apps/${appId}`, {
      headers: { Authorization: `Bearer ${firebaseIdToken}` },
    });
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.name).toBe('E2E Test App');
  });

  test('POST /clients creates client', async () => {
    const req = await getApiRequest();
    const response = await req.post('/clients', {
      headers: {
        Authorization: `Bearer ${firebaseIdToken}`,
        'Content-Type': 'application/json',
      },
      data: {
        app_id: appId,
        name: 'E2E Test Client',
      },
    });
    expect(response.status()).toBe(201);
    const body = await response.json();
    // ClientInfoWithToken: { client_info: { client_id, ... }, client_token: "refresh_jwt" }
    expect(body).toHaveProperty('client_info');
    expect(body).toHaveProperty('client_token');
    expect(body.client_info).toHaveProperty('client_id');
    clientId = body.client_info.client_id as string;
    refreshToken = body.client_token as string;
  });

  test('GET /clients returns list', async () => {
    const req = await getApiRequest();
    const response = await req.get('/clients', {
      headers: { Authorization: `Bearer ${firebaseIdToken}` },
    });
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(Array.isArray(body)).toBe(true);
    expect(body.length).toBeGreaterThanOrEqual(1);
  });

  test('PUT /client_token exchanges refresh for access token', async () => {
    // The controller reads the raw request body directly (not JSON-parsed).
    // It returns the raw access JWT string with Content-Type: application/jose.
    const req = await playwrightRequest.newContext({
      baseURL: process.env.API_BASE_URL || 'http://e2e-backend',
    });
    const response = await req.put('/client_token', {
      headers: { 'Content-Type': 'text/plain' },
      data: refreshToken,
    });
    expect(response.status()).toBe(200);
    // Response body is a raw JWT string, not JSON
    accessToken = await response.text();
    expect(accessToken).toBeTruthy();
    // A JWT has exactly 3 dot-separated segments
    expect(accessToken.split('.').length).toBe(3);
  });

  test('POST /offer registers offer as provider', async () => {
    const req = await getApiRequest();
    const fakeOffer = Buffer.from('v=0\r\no=- 0 0 IN IP4 127.0.0.1\r\n').toString('base64');
    const response = await req.post('/offer', {
      headers: {
        Authorization: `Bearer ${accessToken}`,
        'X-Client-Id': clientId,
        'Content-Type': 'application/json',
      },
      data: {
        role: 'provider',
        offer: fakeOffer,
        established_clients: [],
      },
    });
    expect(response.status()).toBe(201);
    const body = await response.json();
    // PostSDPOfferInfoResponse: { registered_offer?, received_offers? }
    // For provider role: registered_offer is set; received_offers appears when answerers exist
    expect(body).toHaveProperty('registered_offer');
    if (body.registered_offer?.sdp_id) {
      offerSdpId = body.registered_offer.sdp_id as string;
    }
  });

  test('DELETE /exchange/{sdpId} deletes SDP exchange', async () => {
    if (!offerSdpId) {
      test.skip();
      return;
    }
    const req = await getApiRequest();
    const response = await req.delete(`/exchange/${offerSdpId}`, {
      headers: {
        Authorization: `Bearer ${accessToken}`,
        'X-Client-Id': clientId,
      },
    });
    // 204 = deleted, 404 = already gone (both acceptable)
    expect([204, 404]).toContain(response.status());
  });

  test('GET /admin/logs returns log list', async () => {
    const req = await getApiRequest();
    const response = await req.get('/admin/logs', {
      headers: { Authorization: `Bearer ${firebaseIdToken}` },
    });
    expect(response.status()).toBe(200);
    const body = await response.json();
    // Response: { logs: [...] }
    expect(body).toHaveProperty('logs');
    expect(Array.isArray(body.logs)).toBe(true);
  });

  test('DELETE /clients/{clientId} deletes client', async () => {
    const req = await getApiRequest();
    const response = await req.delete(`/clients/${clientId}`, {
      headers: { Authorization: `Bearer ${firebaseIdToken}` },
    });
    expect(response.status()).toBe(204);
  });

  test('GET /clients/{clientId} returns 404 after delete', async () => {
    const req = await getApiRequest();
    const response = await req.get(`/clients/${clientId}`, {
      headers: { Authorization: `Bearer ${firebaseIdToken}` },
    });
    expect(response.status()).toBe(404);
  });
});
