import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function Header() {
  const [menuOpen, setMenuOpen] = useState(false)
  const { logout } = useAuth()
  const location = useLocation()

  const toggleMenu = () => setMenuOpen(!menuOpen)
  const closeMenu = () => setMenuOpen(false)

  const handleLogout = () => {
    closeMenu()
    logout()
  }

  const isActive = (path) => location.pathname === path

  return (
    <header className="header">
      <Link to="/" className="header-logo" onClick={closeMenu}>
        alecnfriends
      </Link>

      <button
        className={`hamburger ${menuOpen ? 'open' : ''}`}
        onClick={toggleMenu}
        aria-label="Menu"
        aria-expanded={menuOpen}
      >
        <span></span>
        <span></span>
        <span></span>
      </button>

      <nav className={`nav-menu ${menuOpen ? 'open' : ''}`}>
        <Link
          to="/"
          className={`nav-link ${isActive('/') ? 'active' : ''}`}
          onClick={closeMenu}
        >
          Games
        </Link>
        <Link
          to="/friends"
          className={`nav-link ${isActive('/friends') ? 'active' : ''}`}
          onClick={closeMenu}
        >
          Friends
        </Link>
        <Link
          to="/profile"
          className={`nav-link ${isActive('/profile') ? 'active' : ''}`}
          onClick={closeMenu}
        >
          Profile
        </Link>
        <button className="nav-link nav-logout" onClick={handleLogout}>
          Logout
        </button>
      </nav>

      {menuOpen && <div className="nav-overlay" onClick={closeMenu}></div>}
    </header>
  )
}
