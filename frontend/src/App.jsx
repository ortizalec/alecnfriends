import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from './context/AuthContext'
import Login from './pages/Login'
import Register from './pages/Register'
import Games from './pages/Games'
import ScrabbleHome from './pages/ScrabbleHome'
import ScrabbleGame from './pages/ScrabbleGame'
import Friends from './pages/Friends'
import Profile from './pages/Profile'

function ProtectedRoute({ children }) {
  const { user, loading } = useAuth()

  if (loading) {
    return <div className="loading-screen">Loading...</div>
  }

  if (!user) {
    return <Navigate to="/login" replace />
  }

  return children
}

function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <Games />
          </ProtectedRoute>
        }
      />
      <Route
        path="/scrabble"
        element={
          <ProtectedRoute>
            <ScrabbleHome />
          </ProtectedRoute>
        }
      />
      <Route
        path="/scrabble/:id"
        element={
          <ProtectedRoute>
            <ScrabbleGame />
          </ProtectedRoute>
        }
      />
      <Route
        path="/friends"
        element={
          <ProtectedRoute>
            <Friends />
          </ProtectedRoute>
        }
      />
      <Route
        path="/profile"
        element={
          <ProtectedRoute>
            <Profile />
          </ProtectedRoute>
        }
      />
    </Routes>
  )
}

export default App
