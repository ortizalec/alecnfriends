import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import Header from '../components/Header'
import { api } from '../services/api'

export default function Games() {
  const navigate = useNavigate()
  const [scrabbleCount, setScrabbleCount] = useState(0)
  const [battleshipCount, setBattleshipCount] = useState(0)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadCounts()
  }, [])

  const loadCounts = async () => {
    try {
      const [scrabbleData, battleshipData] = await Promise.all([
        api.getScrabbleGames(),
        api.getBattleshipGames().catch(() => ({ your_turn: [], their_turn: [] })),
      ])
      const scrabbleActive = (scrabbleData.your_turn?.length || 0) + (scrabbleData.their_turn?.length || 0)
      const battleshipActive = (battleshipData.your_turn?.length || 0) + (battleshipData.their_turn?.length || 0)
      setScrabbleCount(scrabbleActive)
      setBattleshipCount(battleshipActive)
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

          <button
            className="game-hub-card"
            onClick={() => navigate('/battleship')}
          >
            <div className="game-hub-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="2" />
                <path d="M12 2v4M12 18v4M2 12h4M18 12h4" />
                <circle cx="12" cy="12" r="9" />
              </svg>
            </div>
            <div className="game-hub-info">
              <h2 className="game-hub-title">Battleship</h2>
              <p className="game-hub-description">Naval strategy game</p>
            </div>
            {!loading && battleshipCount > 0 && (
              <span className="game-hub-badge">{battleshipCount}</span>
            )}
            <span className="game-hub-arrow">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M9 18l6-6-6-6" />
              </svg>
            </span>
          </button>
        </div>
      </main>
    </div>
  )
}
