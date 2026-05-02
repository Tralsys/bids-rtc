import { test, expect, request as playwrightRequest } from '@playwright/test';

// Global test state — populated sequentially by each test
let firebaseIdToken: string;
let appId: string;
let clientId: string;
let refreshToken: string;
let accessToken: string;
let offerSdpId: string;
// Second client for SDP exchange roundtrip
let clientBId: string;
let clientBRefreshToken: string;
let clientBAccessToken: string;

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

  test('GET /apps/{appId} without Authorization returns 401', async () => {
    const req = await getApiRequest();
    const response = await req.get(`/apps/${appId}`);
    expect(response.status()).toBe(401);
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

  test('PUT /client_token with empty body returns 400', async () => {
    const req = await playwrightRequest.newContext({
      baseURL: process.env.API_BASE_URL || 'http://e2e-backend',
    });
    const response = await req.put('/client_token', {
      headers: { 'Content-Type': 'text/plain' },
      data: '',
    });
    expect(response.status()).toBe(400);
  });

  test('PUT /client_token with invalid token returns 400 or 401', async () => {
    const req = await playwrightRequest.newContext({
      baseURL: process.env.API_BASE_URL || 'http://e2e-backend',
    });
    const response = await req.put('/client_token', {
      headers: { 'Content-Type': 'text/plain' },
      data: 'not.a.valid.jwt',
    });
    expect([400, 401]).toContain(response.status());
  });

  // -----------------------------------------------------------------------
  // POST /client_token/rotate tests
  // -----------------------------------------------------------------------

  test('POST /client_token/rotate with empty body returns 400', async () => {
    const req = await playwrightRequest.newContext({
      baseURL: process.env.API_BASE_URL || 'http://e2e-backend',
    });
    const response = await req.post('/client_token/rotate', {
      headers: { 'Content-Type': 'text/plain' },
      data: '',
    });
    expect(response.status()).toBe(400);
  });

  test('POST /client_token/rotate with invalid token returns 400 or 401', async () => {
    const req = await playwrightRequest.newContext({
      baseURL: process.env.API_BASE_URL || 'http://e2e-backend',
    });
    const response = await req.post('/client_token/rotate', {
      headers: { 'Content-Type': 'text/plain' },
      data: 'not.a.valid.jwt',
    });
    expect([400, 401]).toContain(response.status());
  });

  test('POST /client_token/rotate returns new token pair and old token becomes invalid', async () => {
    const req = await playwrightRequest.newContext({
      baseURL: process.env.API_BASE_URL || 'http://e2e-backend',
    });
    const originalRefreshToken = refreshToken;

    // Rotate: send current refresh token, receive new pair
    const rotateResp = await req.post('/client_token/rotate', {
      headers: { 'Content-Type': 'text/plain' },
      data: originalRefreshToken,
    });
    expect(rotateResp.status()).toBe(200);
    const body = await rotateResp.json();
    expect(body).toHaveProperty('refresh_token');
    expect(body).toHaveProperty('access_token');
    expect((body.refresh_token as string).split('.').length).toBe(3);
    expect((body.access_token as string).split('.').length).toBe(3);

    // Update module-level tokens so subsequent tests use the rotated tokens
    refreshToken = body.refresh_token as string;
    accessToken  = body.access_token as string;

    // Old refresh token must now be rejected (token rotation security property)
    const oldTokenResp = await req.post('/client_token/rotate', {
      headers: { 'Content-Type': 'text/plain' },
      data: originalRefreshToken,
    });
    expect(oldTokenResp.status()).toBe(401);
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

  // SDP exchange roundtrip: Client B as subscriber receives Client A's provider offer
  test('POST /clients creates client B for SDP roundtrip', async () => {
    const req = await getApiRequest();
    const response = await req.post('/clients', {
      headers: {
        Authorization: `Bearer ${firebaseIdToken}`,
        'Content-Type': 'application/json',
      },
      data: {
        app_id: appId,
        name: 'E2E Test Client B',
      },
    });
    expect(response.status()).toBe(201);
    const body = await response.json();
    clientBId = body.client_info.client_id as string;
    clientBRefreshToken = body.client_token as string;
  });

  test('PUT /client_token exchanges refresh token for client B', async () => {
    const req = await playwrightRequest.newContext({
      baseURL: process.env.API_BASE_URL || 'http://e2e-backend',
    });
    const response = await req.put('/client_token', {
      headers: { 'Content-Type': 'text/plain' },
      data: clientBRefreshToken,
    });
    expect(response.status()).toBe(200);
    clientBAccessToken = await response.text();
    expect(clientBAccessToken.split('.').length).toBe(3);
  });

  test('POST /offer registers offer as subscriber (Client B) and receives provider offer', async () => {
    if (!offerSdpId) {
      test.skip();
      return;
    }
    const req = await getApiRequest();
    const fakeOffer = Buffer.from('v=0\r\no=- 1 1 IN IP4 127.0.0.1\r\n').toString('base64');
    const response = await req.post('/offer', {
      headers: {
        Authorization: `Bearer ${clientBAccessToken}`,
        'X-Client-Id': clientBId,
        'Content-Type': 'application/json',
      },
      data: {
        role: 'subscriber',
        offer: fakeOffer,
        established_clients: [],
      },
    });
    expect(response.status()).toBe(201);
    const body = await response.json();
    expect(body).toHaveProperty('received_offers');
    expect(Array.isArray(body.received_offers)).toBe(true);
    expect(body.received_offers.length).toBeGreaterThanOrEqual(1);
    // Verify we received the provider's offer
    const receivedOffer = body.received_offers.find((o: { sdp_id: string }) => o.sdp_id === offerSdpId);
    expect(receivedOffer).toBeTruthy();
  });

  test('POST /answer registers answer from subscriber (Client B)', async () => {
    if (!offerSdpId) {
      test.skip();
      return;
    }
    const req = await getApiRequest();
    const fakeAnswer = Buffer.from('v=0\r\no=- 2 2 IN IP4 127.0.0.1\r\n').toString('base64');
    const response = await req.post('/answer', {
      headers: {
        Authorization: `Bearer ${clientBAccessToken}`,
        'X-Client-Id': clientBId,
        'Content-Type': 'application/json',
      },
      data: [{ sdp_id: offerSdpId, answer: fakeAnswer }],
    });
    expect(response.status()).toBe(201);
  });

  test('GET /answer/{sdpId} provider receives answer from subscriber', async () => {
    if (!offerSdpId) {
      test.skip();
      return;
    }
    const req = await getApiRequest();
    const response = await req.get(`/answer/${offerSdpId}`, {
      headers: {
        Authorization: `Bearer ${accessToken}`,
        'X-Client-Id': clientId,
      },
    });
    // 200 = answer available, 204 = timeout (both are acceptable outcomes)
    expect([200, 204]).toContain(response.status());
    if (response.status() === 200) {
      const body = await response.json();
      expect(body).toHaveProperty('answer');
    }
  });

  test('DELETE /clients/{clientBId} deletes client B', async () => {
    if (!clientBId) {
      test.skip();
      return;
    }
    const req = await getApiRequest();
    const response = await req.delete(`/clients/${clientBId}`, {
      headers: { Authorization: `Bearer ${firebaseIdToken}` },
    });
    expect(response.status()).toBe(204);
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
