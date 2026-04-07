import { boot } from 'quasar/wrappers'
import axios from 'axios'
import { Notify } from 'quasar'
import router from '../router' // Adjust the path as needed

const isInvalidToken = (token) => {
  if (!token) return true
  const normalized = String(token).trim().toLowerCase()
  return normalized === 'undefined' || normalized === 'null' || normalized === ''
}

const clearAuthStorage = () => {
  localStorage.removeItem('jwt_token')
  localStorage.removeItem('username')
  localStorage.removeItem('user_id')
}

// Create an Axios instance with the base URL
const api = axios.create({
  baseURL: 'http://localhost:8000/api/',  // Replace with your backend API URL
  headers: {
    'Content-Type': 'application/json',
  },
})

// Request interceptor to attach the token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('jwt_token')
    if (isInvalidToken(token)) {
      clearAuthStorage()
      return config
    }
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    // Handle request errors
    return Promise.reject(error)
  }
)

// Response interceptor to handle errors
api.interceptors.response.use(
  (response) => {
    return response
  },
  (error) => {
    // Check for 401 Unauthorized response
    if (error.response && error.response.status === 401) {
      Notify.create({
        message: 'Session expired, please login again',
        color: 'negative',
      })
      clearAuthStorage()
      router.push('/login') // Redirect to login page
    }
    return Promise.reject(error) // Forward error
  }
)

export default boot(({ app }) => {
  const token = localStorage.getItem('jwt_token')
  if (isInvalidToken(token)) {
    clearAuthStorage()
  }

  // Allow use of this.$axios (for Vue Options API form)
  app.config.globalProperties.$axios = axios

  // Allow use of this.$api (for Vue Options API form)
  app.config.globalProperties.$api = api
})

// Export the Axios instance for use in other modules
export { api }
