import Header from '../components/Header'
import { useAuth } from '../context/AuthContext'

export default function Profile() {
  const { user } = useAuth()

  return (
    <div className="page">
      <Header />
      <main className="container main-content">
        <h1 className="page-title">Profile</h1>
        <div className="card">
          <div className="profile-info">
            <div className="profile-row">
              <span className="profile-label">Username</span>
              <span className="profile-value">{user?.username}</span>
            </div>
            <div className="profile-row">
              <span className="profile-label">Member since</span>
              <span className="profile-value">
                {user?.created_at ? new Date(user.created_at).toLocaleDateString() : '-'}
              </span>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}
