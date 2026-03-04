import axios from "axios";

const API_BASE_URL = import.meta.env.VITE_API_URL || "http://localhost:8000";

const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export async function login(email, password) {
  const { data } = await api.post("/api/auth/login", { email, password });
  return data;
}

export async function register(email, password) {
  const { data } = await api.post("/api/auth/register", { email, password });
  return data;
}

export async function getCurrentUser() {
  const { data } = await api.get("/api/auth/me");
  return data;
}

export async function getNotificationsCount() {
  const { data } = await api.get("/api/notifications/count");
  return data;
}

export default api;
