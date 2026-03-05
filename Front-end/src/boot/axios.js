import { boot } from 'quasar/wrappers'
import axios from 'axios'
import { Notify } from 'quasar'
import router from '../router' // Adjust the path as needed

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
      localStorage.removeItem('jwt_token')
      router.push('/login') // Redirect to login page
    }
    return Promise.reject(error) // Forward error
  }
)

export default boot(({ app }) => {
  // Allow use of this.$axios (for Vue Options API form)
  app.config.globalProperties.$axios = axios

  // Allow use of this.$api (for Vue Options API form)
  app.config.globalProperties.$api = api
})

// Export the Axios instance for use in other modules
export { api }
