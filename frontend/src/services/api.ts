import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('plumora-access-token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

let billingBlockedAlertShown = false

api.interceptors.response.use(
  (res) => res,
  async (err) => {
    const originalRequest = err.config
    if (err.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true
      const refreshToken = localStorage.getItem('plumora-refresh-token')
      if (refreshToken) {
        try {
          const { data } = await axios.post('/api/auth/refresh', { refresh_token: refreshToken })
          localStorage.setItem('plumora-access-token', data.access_token)
          localStorage.setItem('plumora-refresh-token', data.refresh_token)
          originalRequest.headers.Authorization = `Bearer ${data.access_token}`
          return api(originalRequest)
        } catch {
          localStorage.removeItem('plumora-access-token')
          localStorage.removeItem('plumora-refresh-token')
          window.location.href = '/login'
        }
      }
    }
    // Assinatura vencida/inativa (ver check_subscription_active no backend) — manda pro
    // perfil pra regularizar, sem apagar nada. Evita alertas repetidos em telas com várias
    // chamadas simultâneas.
    if (err.response?.status === 402 && !billingBlockedAlertShown && !window.location.pathname.startsWith('/app/profile')) {
      billingBlockedAlertShown = true
      alert(err.response.data?.detail || 'Sua assinatura está vencida — regularize para continuar usando o Plumora.')
      window.location.href = '/app/profile'
    }
    return Promise.reject(err)
  },
)

export default api

// Auth
export const authApi = {
  login: (email: string, password: string) =>
    api.post('/auth/login', new URLSearchParams({ username: email, password }), {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    }),
  register: (data: { name: string; email: string; password: string; role?: string }) =>
    api.post('/auth/register', data),
  me: () => api.get('/auth/me'),
  logout: () => { localStorage.removeItem('plumora-access-token'); localStorage.removeItem('plumora-refresh-token') },
}

// Birds
export const birdsApi = {
  list: (params?: { status?: string; species_id?: string }) => api.get('/birds', { params }),
  create: (data: unknown) => api.post('/birds', data),
  get: (id: string) => api.get(`/birds/${id}`),
  update: (id: string, data: unknown) => api.put(`/birds/${id}`, data),
  delete: (id: string) => api.delete(`/birds/${id}`),
  stats: (id: string) => api.get(`/birds/${id}/stats`),
  species: () => api.get('/birds/species/all'),
  breeds: (speciesId: string) => api.get(`/birds/breeds/by-species/${speciesId}`),
  exportCsv: () => api.get('/birds/export', { responseType: 'blob' }),
}

// Health
export const healthApi = {
  list: (birdId: string) => api.get(`/birds/${birdId}/health`),
  create: (birdId: string, data: unknown) => api.post(`/birds/${birdId}/health`, data),
  requestVet: (birdId: string, recordId: string, data: unknown) =>
    api.post(`/birds/${birdId}/health/${recordId}/request-vet`, data),
  myVetConsults: () => api.get('/birds/vet-consults/mine'),
}

// Dashboard
export const dashboardApi = {
  summary: () => api.get('/dashboard/summary'),
}

// Vaccines
export const vaccinesApi = {
  products: () => api.get('/vaccines/products'),
  records: (birdId: string) => api.get(`/vaccines/birds/${birdId}/records`),
  createRecord: (data: unknown) => api.post('/vaccines/records', data),
  createLot: (data: unknown) => api.post('/vaccines/lots', data),
  schedule: (days?: number) => api.get('/vaccines/schedule', { params: { days } }),
}

// Cages
export const cagesApi = {
  list: (activeOnly?: boolean) => api.get('/cages', { params: activeOnly ? { active_only: true } : undefined }),
  create: (data: unknown) => api.post('/cages', data),
  update: (id: string, data: unknown) => api.put(`/cages/${id}`, data),
  remove: (id: string) => api.delete(`/cages/${id}`),
}

// Breeding
export const breedingApi = {
  list: () => api.get('/breeding'),
  create: (data: unknown) => api.post('/breeding', data),
  get: (id: string) => api.get(`/breeding/${id}`),
  update: (id: string, data: unknown) => api.put(`/breeding/${id}`, data),
  addClutch: (id: string, data: unknown) => api.post(`/breeding/${id}/clutch`, data),
  listClutches: (id: string) => api.get(`/breeding/${id}/clutches`),
  updateClutch: (clutchId: string, data: unknown) => api.put(`/breeding/clutch/${clutchId}`, data),
  listOffspring: (clutchId: string) => api.get(`/breeding/clutch/${clutchId}/offspring`),
  addOffspring: (clutchId: string, data: unknown) => api.post(`/breeding/clutch/${clutchId}/offspring`, data),
  updateOffspring: (offspringId: string, data: unknown) => api.put(`/breeding/offspring/${offspringId}`, data),
  removeOffspring: (offspringId: string) => api.delete(`/breeding/offspring/${offspringId}`),
  promoteOffspring: (offspringId: string, data: unknown) => api.post(`/breeding/offspring/${offspringId}/promote`, data),
  stats: () => api.get('/breeding/stats/summary'),
}

// Genetics
export const geneticsApi = {
  loci: (speciesId: string) => api.get(`/genetics/species/${speciesId}/loci`),
  getBirdGenotype: (birdId: string) => api.get(`/genetics/birds/${birdId}/genotype`),
  upsertGenotype: (birdId: string, data: unknown) => api.post(`/genetics/birds/${birdId}/genotype`, data),
  getPigeonGenotype: (birdId: string) => api.get(`/genetics/birds/${birdId}/pigeon-genotype`),
  upsertPigeonGenotype: (birdId: string, data: unknown) => api.post(`/genetics/birds/${birdId}/pigeon-genotype`, data),
  calculate: (data: unknown) => api.post('/genetics/calculate', data),
}

// Races
export const racesApi = {
  list: () => api.get('/races'),
  create: (data: unknown) => api.post('/races', data),
  get: (id: string) => api.get(`/races/${id}`),
  addResult: (raceId: string, data: unknown) => api.post(`/races/${raceId}/results`, data),
}

// Pedigree
export const pedigreeApi = {
  get: (birdId: string, generations?: number) =>
    api.get(`/birds/${birdId}/pedigree`, { params: { generations } }),
  export: (birdId: string, generations?: number) =>
    api.post(`/birds/${birdId}/pedigree/export`, null, { params: { generations } }),
}

// Vet
export const vetApi = {
  queue: (status?: string) => api.get('/vet/queue', { params: { status } }),
  getConsult: (id: string) => api.get(`/vet/consults/${id}`),
  respond: (id: string, data: unknown) => api.post(`/vet/consults/${id}/respond`, data),
  patients: () => api.get('/vet/patients'),
}

// Billing (assinatura em si é POST /api/profile/subscribe — ver profileApi/ProfilePage.tsx)
export const billingApi = {
  plans: () => api.get('/billing/plans'),
  cancel: () => api.post('/billing/cancel'),
}

// Admin
export const adminApi = {
  users: (params?: { skip?: number; limit?: number }) => api.get('/admin/users', { params }),
  toggleUser: (id: string, isActive: boolean) => api.put(`/admin/users/${id}/status`, null, { params: { is_active: isActive } }),
  subscriptions: () => api.get('/admin/subscriptions'),
  dashboard: () => api.get('/admin/dashboard'),
}

// Marketing (admin)
export const marketingApi = {
  listTemplates: () => api.get('/admin/marketing/templates'),
  createTemplate: (data: unknown) => api.post('/admin/marketing/templates', data),
  listCampaigns: () => api.get('/admin/marketing/campaigns'),
  getCampaign: (id: string) => api.get(`/admin/marketing/campaigns/${id}`),
  createCampaign: (data: unknown) => api.post('/admin/marketing/campaigns', data),
  previewSegment: (id: string) => api.post(`/admin/marketing/campaigns/${id}/preview`),
  sendCampaign: (id: string) => api.post(`/admin/marketing/campaigns/${id}/send`),
}
