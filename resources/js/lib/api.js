import axios from 'axios'

const BASE = (import.meta.env.VITE_BASE_URL || '/').replace(/\/+$/, '') // sin barra final
export const api = axios.create({
  baseURL: `${BASE}`,
})