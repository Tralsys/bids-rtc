import { test, expect, request as playwrightRequest } from '@playwright/test';

// Global test state
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
  const ctx = await playwrightRequest.newContext({
    baseURL: process.env.API_BASE_URL || 'http://e2e-backend',
    extraHTTPHeaders: { 'Content-Type': 'application/json' },
  });
  return ctx;
}

test.describe('API E2E', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(async () => {
    // Wait for backend to be ready with retries
    const maxRetries = 30;
    for (let i = 0; i < maxRetries; i++) {
      try {
        const req = await playwrightRequest.newContext({ baseURL: process.env.API_BASE_URL || 'http://e2e-backend' });
        const resp = await req.get('/');
        if (resp.ok()) break;
      } catch (e) {
        await new Promise(r => setTimeout(r, 2000));
      }
      if (i === maxRetries - 1) throw new Error('Backend not ready');
    }
  });

  test('GET / returns API info', async () => {
    const req = await getApiRequest();
    const response = await req.get('/');
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('name');
    expect(body).toHaveProperty('version');
  });

  test('Firebase: sign up test user', async () => {
    const req = await playwrightRequest.newContext({
      baseURL: FIREBASE_EMULATOR,
      extraHTTPHeaders: { 'Content-Type': 'application/json' },
    });
    const response = await req.post(
      '/identitytoolkit.googleapis.com/v1/accounts:signUp?key=apikey',
      {
        data: {
          email: EMAIL,
          password: PASSWORD,
          returnSecureToken: true,
        },
      }
    );
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('idToken');
    firebaseIdToken = body.idToken;
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
    expect(body).toHaveProperty('app_id');
    expect(body).toHaveProperty('name');
    expect(body).toHaveProperty('description');
    expect(body).toHaveProperty('owner');
    expect(body).toHaveProperty('created_at');
    appId = body.app_id;
  });

  test('GET /apps/{appId} returns application', async () => {
    const req = await getApiRequest();
    const response = await req.get(`/apps/${appId}`, {
      headers: {
        Authorization: `Bearer ${firebaseIdToken}`,
      },
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
    expect(body).toHaveProperty('client_id');
    expect(body).toHaveProperty('refresh_token');
    clientId = body.client_id;
    refreshToken = body.refresh_token;
  });

  test('GET /clients returns list', async () => {
    const req = await getApiRequest();
    const response = await req.get('/clients', {
      headers: {
        Authorization: `Bearer ${firebaseIdToken}`,
      },
    });
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(Array.isArray(body)).toBe(true);
    expect(body.length).toBeGreaterThanOrEqual(1);
  });

  test('PUT /client_token exchanges refresh for access token', async () => {
    const req = await getApiRequest();
    const response = await req.put('/client_token', {
      headers: {
        'Content-Type': 'application/json',
      },
      data: {
        refresh_token: refreshToken,
      },
    });
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('access_token');
    accessToken = body.access_token;
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
    expect(body).toHaveProperty('received_offers');
    if (body.registered_offer && body.registered_offer.sdp_id) {
      offerSdpId = body.registered_offer.sdp_id;
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
    expect([204, 404]).toContain(response.status());
  });

  test('GET /admin/logs returns log list', async () => {
    const req = await getApiRequest();
    const response = await req.get('/admin/logs', {
      headers: {
        Authorization: `Bearer ${firebaseIdToken}`,
      },
    });
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(Array.isArray(body)).toBe(true);
  });

  test('DELETE /clients/{clientId} deletes client', async () => {
    const req = await getApiRequest();
    const response = await req.delete(`/clients/${clientId}`, {
      headers: {
        Authorization: `Bearer ${firebaseIdToken}`,
      },
    });
    expect(response.status()).toBe(204);
  });

  test('GET /clients/{clientId} returns 404 after delete', async () => {
    const req = await getApiRequest();
    const response = await req.get(`/clients/${clientId}`, {
      headers: {
        Authorization: `Bearer ${firebaseIdToken}`,
      },
    });
    expect(response.status()).toBe(404);
  });
});
