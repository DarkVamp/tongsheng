import { useEffect } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from './context/AuthContext'
import { useLocale } from './context/LocaleContext'
import ProtectedRoute from './components/ProtectedRoute'
import LoginPage from './pages/LoginPage'
import FamilyDashboard from './pages/FamilyDashboard'
import TeacherDashboard from './pages/TeacherDashboard'
import RegisterPage from './pages/RegisterPage'
import './index.css'

function RootRedirect() {
  const { user, loading } = useAuth()
  if (loading) return <div className="loading">Laden…</div>
  if (!user || !user.role) return <Navigate to="/login" replace />
  if (user.role === 'teacher') return <Navigate to="/teacher" replace />
  return <Navigate to="/family" replace />
}

function LocaleSync() {
  const { user } = useAuth()
  const { setLocale } = useLocale()
  useEffect(() => {
    if (user?.locale) setLocale(user.locale)
  }, [user?.locale])
  return null
}

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <LocaleSync />
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/" element={<RootRedirect />} />
          <Route
            path="/family"
            element={
              <ProtectedRoute role={['family', 'family_member']}>
                <FamilyDashboard />
              </ProtectedRoute>
            }
          />
          <Route
            path="/teacher"
            element={<ProtectedRoute role="teacher"><TeacherDashboard /></ProtectedRoute>}
          />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
