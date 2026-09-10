import axios from 'axios'

const BASE = (import.meta.env.VITE_BASE_API || '/').replace(/\/+$/, '') // sin barra final
export const api = axios.create({
  baseURL: `${BASE}`,
})