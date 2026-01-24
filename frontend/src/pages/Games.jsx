import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import Header from '../components/Header'
import { api } from '../services/api'

export default function Games() {
  const navigate = useNavigate()
  const [scrabbleCount, setScrabbleCount] = useState(0)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadCounts()
  }, [])

  const loadCounts = async () => {
    try {
      const gamesData = await api.getScrabbleGames()
      const activeCount = (gamesData.your_turn?.length || 0) + (gamesData.their_turn?.length || 0)
      setScrabbleCount(activeCount)
    } catch {
      // Ignore errors for counts
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="page">
      <Header />
      <main className="container main-content">
        <h1 className="page-title">Games</h1>

        <div className="games-hub">
          <button
            className="game-hub-card"
            onClick={() => navigate('/scrabble')}
          >
            <div className="game-hub-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="3" y="3" width="7" height="7" rx="1" />
                <rect x="14" y="3" width="7" height="7" rx="1" />
                <rect x="3" y="14" width="7" height="7" rx="1" />
                <rect x="14" y="14" width="7" height="7" rx="1" />
              </svg>
            </div>
            <div className="game-hub-info">
              <h2 className="game-hub-title">Scrabble</h2>
              <p className="game-hub-description">Classic word game</p>
            </div>
            {!loading && scrabbleCount > 0 && (
              <span className="game-hub-badge">{scrabbleCount}</span>
            )}
            <span className="game-hub-arrow">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M9 18l6-6-6-6" />
              </svg>
            </span>
          </button>

          {/* More games can be added here */}
        </div>
      </main>
    </div>
  )
}
