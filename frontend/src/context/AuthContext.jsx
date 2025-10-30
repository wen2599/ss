import { createContext, useState, useEffect } from 'react'
import axios from 'axios'

const AuthContext = createContext()

const API_URL = 'http://localhost:8080/api' // Adjust this URL based on your backend setup

function AuthProvider({ children }) {
  const [user, setUser] = useState(null)

  useEffect(() => {
    const token = localStorage.getItem('token')
    if (token) {
      // You might want to validate the token with the backend here
      // For simplicity, we'll just decode it
      try {
        const userData = JSON.parse(atob(token.split('.')[1]))
        setUser(userData)
      } catch (e) {
        console.error("Invalid token", e)
        localStorage.removeItem('token')
      }
    }
  }, [])

  const login = async (email) => {
    const response = await axios.post(`${API_URL}/auth.php`, { email, action: 'login' })
    const { token } = response.data
    localStorage.setItem('token', token)
    const userData = JSON.parse(atob(token.split('.')[1]))
    setUser(userData)
  }

  const register = async (email) => {
    await axios.post(`${API_URL}/auth.php`, { email, action: 'register' })
  }

  const logout = () => {
    localStorage.removeItem('token')
    setUser(null)
  }

  return (
    <AuthContext.Provider value={{ user, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export { AuthProvider, AuthContext }