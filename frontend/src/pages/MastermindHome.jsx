import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import Header from '../components/Header'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/api'

export default function MastermindHome() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [games, setGames] = useState({ your_turn: [], their_turn: [], completed: [] })
  const [friends, setFriends] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [showNewGameModal, setShowNewGameModal] = useState(false)
  const [creating, setCreating] = useState(false)
  const [selectedFriend, setSelectedFriend] = useState(null)
  const [numColors, setNumColors] = useState(6)
  const [allowRepeats, setAllowRepeats] = useState(true)

  useEffect(() => {
    loadData()
  }, [])

  useEffect(() => {
    const interval = setInterval(() => {
      loadData()
    }, 20000)
    return () => clearInterval(interval)
  }, [])

  useEffect(() => {
    const handleVisibility = () => {
      if (document.visibilityState === 'visible') {
        loadData()
      }
    }
    document.addEventListener('visibilitychange', handleVisibility)
    return () => document.removeEventListener('visibilitychange', handleVisibility)
  }, [])

  const loadData = async () => {
    try {
      const [gamesData, friendsData] = await Promise.all([
        api.getMastermindGames(),
        api.getFriends(),
      ])
      setGames(gamesData)
      setFriends(friendsData)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const handleStartGame = async () => {
    if (!selectedFriend) return
    setCreating(true)
    setError('')
    try {
      const result = await api.createMastermindGame(selectedFriend, numColors, allowRepeats)
      setShowNewGameModal(false)
      setSelectedFriend(null)
      navigate(`/mastermind/${result.game.id}`)
    } catch (err) {
      setError(err.message)
    } finally {
      setCreating(false)
    }
  }

  const closeModal = () => {
    setShowNewGameModal(false)
    setSelectedFriend(null)
    setNumColors(6)
    setAllowRepeats(true)
  }

  const getOpponent = (game) => {
    if (game.player1_id === user?.id) {
      return game.player2
    }
    return game.player1
  }

  const getDifficultyLabel = (game) => {
    const colors = game.num_colors || 6
    const repeats = game.allow_repeats !== false
    return `${colors}C${repeats ? '' : ' NR'}`
  }

  const GameCard = ({ game, showStatus }) => {
    const opponent = getOpponent(game)
    const isWinner = game.winner_id === user?.id
    const isLoser = game.winner_id && game.winner_id !== user?.id
    const isDraw = game.status === 'completed' && !game.winner_id
    const isYourTurn = game.current_turn === user?.id && game.status === 'active'
    const isSetup = game.status === 'setup'

    return (
      <li
        className="game-card"
        onClick={() => navigate(`/mastermind/${game.id}`)}
      >
        <div className="game-card-main">
          <span className="game-card-opponent">{opponent?.username}</span>
          <span className="game-card-score text-muted" style={{ fontSize: '0.75rem' }}>
            {getDifficultyLabel(game)}
          </span>
        </div>
        <div className="game-card-status">
          {showStatus && game.status === 'completed' ? (
            <span className={`status-badge ${isWinner ? 'won' : isLoser ? 'lost' : isDraw ? 'draw' : ''}`}>
              {isWinner ? 'Won' : isLoser ? 'Lost' : 'Draw'}
            </span>
          ) : isSetup ? (
            <span className="status-badge your-turn">Setup</span>
          ) : game.status === 'active' && (
            <span className={`status-badge ${isYourTurn ? 'your-turn' : 'waiting'}`}>
              {isYourTurn ? 'Your turn' : 'Waiting'}
            </span>
          )}
        </div>
      </li>
    )
  }

  if (loading) {
    return (
      <div className="page">
        <Header />
        <main className="container main-content">
          <div className="loading-text">Loading...</div>
        </main>
      </div>
    )
  }

  const yourTurnGames = games.your_turn || []
  const theirTurnGames = games.their_turn || []
  const completedGames = games.completed || []

  return (
    <div className="page">
      <Header />
      <main className="container main-content">
        <div className="page-header">
          <h1 className="page-title">Mastermind</h1>
          <button
            className="btn btn-primary"
            onClick={() => setShowNewGameModal(true)}
          >
            New Game
          </button>
        </div>

        {error && <div className="alert alert-error">{error}</div>}

        {yourTurnGames.length > 0 && (
          <section className="game-section">
            <h2 className="section-label">Your Turn</h2>
            <ul className="game-list">
              {yourTurnGames.map((game) => (
                <GameCard key={game.id} game={game} />
              ))}
            </ul>
          </section>
        )}

        {theirTurnGames.length > 0 && (
          <section className="game-section">
            <h2 className="section-label">Their Turn</h2>
            <ul className="game-list">
              {theirTurnGames.map((game) => (
                <GameCard key={game.id} game={game} />
              ))}
            </ul>
          </section>
        )}

        {completedGames.length > 0 && (
          <section className="game-section">
            <h2 className="section-label">Completed</h2>
            <ul className="game-list">
              {completedGames.slice(0, 10).map((game) => (
                <GameCard key={game.id} game={game} showStatus />
              ))}
            </ul>
          </section>
        )}

        {yourTurnGames.length === 0 && theirTurnGames.length === 0 && completedGames.length === 0 && (
          <div className="empty-state">
            <p>No games yet.</p>
            <p className="text-muted">Start a game with a friend!</p>
          </div>
        )}

        {showNewGameModal && (
          <>
            <div className="modal-overlay" onClick={closeModal} />
            <div className="modal">
              <h2 className="modal-title">New Game</h2>
              {friends.length === 0 ? (
                <p className="text-muted">Add some friends first to start a game.</p>
              ) : (
                <>
                  <p className="modal-subtitle">Opponent</p>
                  <ul className="friend-select-list">
                    {friends.map((friendship) => (
                      <li key={friendship.id}>
                        <button
                          className={`friend-select-btn ${selectedFriend === friendship.friend_id ? 'selected' : ''}`}
                          onClick={() => setSelectedFriend(friendship.friend_id)}
                        >
                          {friendship.friend?.username}
                        </button>
                      </li>
                    ))}
                  </ul>

                  <p className="modal-subtitle mt-2">Difficulty</p>
                  <div className="difficulty-options">
                    <div className="option-group">
                      <span className="option-label">Colors:</span>
                      <div className="option-buttons">
                        {[4, 6, 8].map((n) => (
                          <button
                            key={n}
                            className={`option-btn ${numColors === n ? 'selected' : ''}`}
                            onClick={() => setNumColors(n)}
                          >
                            {n}
                          </button>
                        ))}
                      </div>
                    </div>
                    <div className="option-group">
                      <span className="option-label">Repeats:</span>
                      <div className="option-buttons">
                        <button
                          className={`option-btn ${allowRepeats ? 'selected' : ''}`}
                          onClick={() => setAllowRepeats(true)}
                        >
                          Yes
                        </button>
                        <button
                          className={`option-btn ${!allowRepeats ? 'selected' : ''}`}
                          onClick={() => setAllowRepeats(false)}
                        >
                          No
                        </button>
                      </div>
                    </div>
                  </div>

                  <button
                    className="btn btn-primary btn-full mt-2"
                    onClick={handleStartGame}
                    disabled={!selectedFriend || creating}
                  >
                    {creating ? 'Creating...' : 'Start Game'}
                  </button>
                </>
              )}
              <button
                className="btn btn-secondary btn-full mt-1"
                onClick={closeModal}
              >
                Cancel
              </button>
            </div>
          </>
        )}
      </main>
    </div>
  )
}
