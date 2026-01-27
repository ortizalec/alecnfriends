import { useState, useEffect, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import Header from '../components/Header'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/api'

const COLORS = [
  { name: 'red', hex: '#e53935' },
  { name: 'orange', hex: '#fb8c00' },
  { name: 'yellow', hex: '#fdd835' },
  { name: 'green', hex: '#43a047' },
  { name: 'blue', hex: '#1e88e5' },
  { name: 'purple', hex: '#8e24aa' },
  { name: 'pink', hex: '#ec407a' },
  { name: 'brown', hex: '#6d4c41' },
]

const CODE_LENGTH = 4
const MAX_GUESSES = 10

export default function MastermindGame() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()

  const [game, setGame] = useState(null)
  const [mySecret, setMySecret] = useState([])
  const [opponentSecret, setOpponentSecret] = useState([])
  const [myGuesses, setMyGuesses] = useState([])
  const [theirGuesses, setTheirGuesses] = useState([])
  const [secretSet, setSecretSet] = useState(false)
  const [isYourTurn, setIsYourTurn] = useState(false)
  const [phase, setPhase] = useState('setup')
  const [round, setRound] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  // Setup state
  const [setupCode, setSetupCode] = useState([null, null, null, null])
  const [selectedSlot, setSelectedSlot] = useState(0)

  // Guess state
  const [currentGuess, setCurrentGuess] = useState([null, null, null, null])
  const [selectedGuessSlot, setSelectedGuessSlot] = useState(0)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const loadGame = useCallback(async () => {
    try {
      const data = await api.getMastermindGame(id)
      setGame(data.game)
      setMySecret(data.my_secret || [])
      setOpponentSecret(data.opponent_secret || [])
      setMyGuesses(data.my_guesses || [])
      setTheirGuesses(data.their_guesses || [])
      setSecretSet(data.secret_set)
      setIsYourTurn(data.is_your_turn)
      setPhase(data.phase)
      setRound(data.round)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadGame()
  }, [loadGame])

  // Poll for updates when waiting
  useEffect(() => {
    if (!game || phase === 'completed') return
    if (phase === 'active' && isYourTurn) return
    if (phase === 'setup' && !secretSet) return

    const interval = setInterval(() => {
      loadGame()
    }, 5000)

    return () => clearInterval(interval)
  }, [game, phase, isYourTurn, secretSet, loadGame])

  // Refresh when tab becomes visible
  useEffect(() => {
    const handleVisibility = () => {
      if (document.visibilityState === 'visible') {
        loadGame()
      }
    }
    document.addEventListener('visibilitychange', handleVisibility)
    return () => document.removeEventListener('visibilitychange', handleVisibility)
  }, [loadGame])

  const getOpponent = () => {
    if (!game || !user) return null
    return game.player1_id === user.id ? game.player2 : game.player1
  }

  const handleSetupColorSelect = (colorIndex) => {
    const newCode = [...setupCode]
    newCode[selectedSlot] = colorIndex
    setSetupCode(newCode)
    // Auto-advance to next slot
    if (selectedSlot < CODE_LENGTH - 1) {
      setSelectedSlot(selectedSlot + 1)
    }
  }

  const handleSetupSlotClick = (index) => {
    setSelectedSlot(index)
  }

  const handleClearSetup = () => {
    setSetupCode([null, null, null, null])
    setSelectedSlot(0)
  }

  const handleSubmitSecret = async () => {
    if (setupCode.some(c => c === null)) return

    setError('')
    setIsSubmitting(true)
    try {
      await api.setMastermindSecret(id, setupCode)
      await loadGame()
      setMessage('Secret code set! Waiting for opponent...')
      setTimeout(() => setMessage(''), 3000)
    } catch (err) {
      setError(err.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleGuessColorSelect = (colorIndex) => {
    const newGuess = [...currentGuess]
    newGuess[selectedGuessSlot] = colorIndex
    setCurrentGuess(newGuess)
    // Auto-advance to next slot
    if (selectedGuessSlot < CODE_LENGTH - 1) {
      setSelectedGuessSlot(selectedGuessSlot + 1)
    }
  }

  const handleGuessSlotClick = (index) => {
    setSelectedGuessSlot(index)
  }

  const handleClearGuess = () => {
    setCurrentGuess([null, null, null, null])
    setSelectedGuessSlot(0)
  }

  const handleSubmitGuess = async () => {
    if (currentGuess.some(c => c === null) || isSubmitting) return

    setError('')
    setIsSubmitting(true)
    try {
      const result = await api.makeMastermindGuess(id, currentGuess)
      setCurrentGuess([null, null, null, null])
      setSelectedGuessSlot(0)

      // Check the latest guess for feedback message
      const latestGuess = result.my_guesses[result.my_guesses.length - 1]
      if (latestGuess) {
        if (latestGuess.correct === CODE_LENGTH) {
          setMessage('You cracked the code!')
        } else {
          setMessage(`${latestGuess.correct} correct, ${latestGuess.misplaced} misplaced`)
        }
        setTimeout(() => setMessage(''), 3000)
      }

      await loadGame()
    } catch (err) {
      setError(err.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleResign = async () => {
    if (!window.confirm('Resign this game?')) return
    setError('')

    try {
      await api.resignMastermindGame(id)
      await loadGame()
    } catch (err) {
      setError(err.message)
    }
  }

  const renderPeg = (colorIndex, size = 'normal', onClick = null, isSelected = false) => {
    const color = colorIndex !== null && colorIndex !== undefined ? COLORS[colorIndex] : null
    const sizeClass = size === 'small' ? 'mastermind-peg-small' : 'mastermind-peg'
    return (
      <div
        className={`${sizeClass} ${isSelected ? 'selected' : ''} ${onClick ? 'clickable' : ''}`}
        style={{ backgroundColor: color ? color.hex : 'transparent' }}
        onClick={onClick}
      />
    )
  }

  const renderFeedback = (correct, misplaced) => {
    const pegs = []
    for (let i = 0; i < correct; i++) {
      pegs.push(<div key={`c${i}`} className="feedback-peg correct" />)
    }
    for (let i = 0; i < misplaced; i++) {
      pegs.push(<div key={`m${i}`} className="feedback-peg misplaced" />)
    }
    // Fill empty slots
    for (let i = correct + misplaced; i < CODE_LENGTH; i++) {
      pegs.push(<div key={`e${i}`} className="feedback-peg empty" />)
    }
    return <div className="feedback-container">{pegs}</div>
  }

  const renderGuessRow = (guess, index, isOpponent = false) => {
    return (
      <div key={guess.id || index} className={`mastermind-row ${isOpponent ? 'opponent' : ''}`}>
        <span className="row-number">{guess.guess_number}</span>
        <div className="guess-pegs">
          {guess.guess.map((colorIndex, i) => (
            <div key={i}>{renderPeg(colorIndex)}</div>
          ))}
        </div>
        {renderFeedback(guess.correct, guess.misplaced)}
      </div>
    )
  }

  const renderEmptyRows = (count, startNumber) => {
    const rows = []
    for (let i = 0; i < count; i++) {
      rows.push(
        <div key={`empty-${i}`} className="mastermind-row empty">
          <span className="row-number">{startNumber + i}</span>
          <div className="guess-pegs">
            {Array(CODE_LENGTH).fill(null).map((_, j) => (
              <div key={j} className="mastermind-peg empty" />
            ))}
          </div>
          <div className="feedback-container">
            {Array(CODE_LENGTH).fill(null).map((_, j) => (
              <div key={j} className="feedback-peg empty" />
            ))}
          </div>
        </div>
      )
    }
    return rows
  }

  if (loading) {
    return (
      <div className="page mastermind-page">
        <Header />
        <main className="container main-content">
          <p className="mastermind-loading">Loading...</p>
        </main>
      </div>
    )
  }

  if (!game) {
    return (
      <div className="page mastermind-page">
        <Header />
        <main className="container main-content">
          <p className="mastermind-loading">Game not found</p>
          <button className="btn btn-primary mt-2" onClick={() => navigate('/mastermind')}>
            Back to Games
          </button>
        </main>
      </div>
    )
  }

  const opponent = getOpponent()

  // Setup Phase - Set Secret Code
  if (phase === 'setup' && !secretSet) {
    return (
      <div className="page mastermind-page">
        <Header />
        <main className="mastermind-container">
          <div className="mastermind-header">
            <h2>Set Your Secret Code</h2>
            <p className="mastermind-subtitle">Choose 4 colors for your opponent to guess</p>
          </div>

          {error && <div className="alert alert-error">{error}</div>}

          <div className="mastermind-setup">
            <div className="secret-code-display">
              {setupCode.map((colorIndex, i) => (
                <div
                  key={i}
                  className={`setup-slot ${selectedSlot === i ? 'selected' : ''}`}
                  onClick={() => handleSetupSlotClick(i)}
                >
                  {renderPeg(colorIndex, 'normal', null, selectedSlot === i)}
                </div>
              ))}
            </div>

            <div className="color-palette">
              {COLORS.map((color, i) => (
                <button
                  key={i}
                  className="color-btn"
                  style={{ backgroundColor: color.hex }}
                  onClick={() => handleSetupColorSelect(i)}
                  title={color.name}
                />
              ))}
            </div>

            <div className="mastermind-actions">
              <button className="btn btn-secondary" onClick={handleClearGuess}>
                Clear
              </button>
              <button
                className="btn btn-primary"
                onClick={handleSubmitSecret}
                disabled={setupCode.some(c => c === null) || isSubmitting}
              >
                {isSubmitting ? 'Setting...' : 'Confirm Secret'}
              </button>
            </div>
          </div>
        </main>
      </div>
    )
  }

  // Waiting for opponent to set secret
  if (phase === 'setup' && secretSet) {
    return (
      <div className="page mastermind-page">
        <Header />
        <main className="mastermind-container">
          <div className="mastermind-header">
            <h2>Waiting for Opponent</h2>
            <p className="mastermind-subtitle">{opponent?.username} is setting their secret code...</p>
          </div>

          <div className="mastermind-waiting">
            <div className="your-secret-preview">
              <span className="label">Your Secret:</span>
              <div className="secret-pegs">
                {mySecret.map((colorIndex, i) => (
                  <div key={i}>{renderPeg(colorIndex)}</div>
                ))}
              </div>
            </div>
          </div>
        </main>
      </div>
    )
  }

  // Active/Completed game
  const myGuessCount = myGuesses.length
  const theirGuessCount = theirGuesses.length
  const myEmptyRows = MAX_GUESSES - myGuessCount
  const theirEmptyRows = MAX_GUESSES - theirGuessCount

  return (
    <div className="page mastermind-page">
      <Header />
      <main className="mastermind-container">
        {/* Header */}
        <div className="mastermind-header">
          <div className="mastermind-status">
            <span className={`player-name ${!isYourTurn && phase === 'active' ? 'current' : ''}`}>
              {opponent?.username}
            </span>
            <span className="vs">vs</span>
            <span className={`player-name ${isYourTurn && phase === 'active' ? 'current' : ''}`}>
              You
            </span>
            {phase === 'completed' && (
              <span className="game-result">
                {game.winner_id === user?.id ? 'You Won!' : game.winner_id ? 'You Lost' : 'Draw'}
              </span>
            )}
          </div>
          <div className="round-indicator">Round {round}/{MAX_GUESSES}</div>
        </div>

        {error && <div className="alert alert-error">{error}</div>}

        {/* Boards */}
        <div className="mastermind-boards">
          {/* Opponent's guesses (their attempts at your code) */}
          <div className="mastermind-board opponent-board">
            <div className="board-header">
              <span className="board-title">{opponent?.username}'s Guesses</span>
              <div className="secret-display">
                <span>Your Code:</span>
                <div className="mini-code">
                  {mySecret.map((colorIndex, i) => (
                    <div key={i}>{renderPeg(colorIndex, 'small')}</div>
                  ))}
                </div>
              </div>
            </div>
            <div className="board-rows">
              {theirGuesses.map((g, i) => renderGuessRow(g, i, true))}
              {theirEmptyRows > 0 && renderEmptyRows(Math.min(theirEmptyRows, 3), theirGuessCount + 1)}
            </div>
          </div>

          {/* Your guesses (your attempts at their code) */}
          <div className="mastermind-board your-board">
            <div className="board-header">
              <span className="board-title">Your Guesses</span>
              <div className="secret-display">
                <span>Their Code:</span>
                <div className="mini-code">
                  {phase === 'completed' && opponentSecret.length > 0 ? (
                    opponentSecret.map((colorIndex, i) => (
                      <div key={i}>{renderPeg(colorIndex, 'small')}</div>
                    ))
                  ) : (
                    Array(CODE_LENGTH).fill(null).map((_, i) => (
                      <div key={i} className="mastermind-peg-small mystery">?</div>
                    ))
                  )}
                </div>
              </div>
            </div>
            <div className="board-rows">
              {myGuesses.map((g, i) => renderGuessRow(g, i))}
              {myEmptyRows > 0 && renderEmptyRows(Math.min(myEmptyRows, 3), myGuessCount + 1)}
            </div>
          </div>
        </div>

        {/* Guess input (only when active and your turn) */}
        {phase === 'active' && (
          <div className="mastermind-input">
            <div className="current-guess">
              {currentGuess.map((colorIndex, i) => (
                <div
                  key={i}
                  className={`guess-slot ${selectedGuessSlot === i ? 'selected' : ''}`}
                  onClick={() => handleGuessSlotClick(i)}
                >
                  {renderPeg(colorIndex, 'normal', null, selectedGuessSlot === i)}
                </div>
              ))}
            </div>

            <div className="color-palette">
              {COLORS.map((color, i) => (
                <button
                  key={i}
                  className="color-btn"
                  style={{ backgroundColor: color.hex }}
                  onClick={() => handleGuessColorSelect(i)}
                  disabled={!isYourTurn}
                  title={color.name}
                />
              ))}
            </div>

            <div className="mastermind-actions">
              <button className="btn btn-secondary" onClick={handleResign}>
                Resign
              </button>
              <div className="mastermind-console">
                {message || (isYourTurn ? 'Make your guess' : 'Waiting for opponent...')}
              </div>
              <button
                className="btn btn-primary"
                onClick={handleSubmitGuess}
                disabled={!isYourTurn || currentGuess.some(c => c === null) || isSubmitting}
              >
                {isSubmitting ? 'Guessing...' : 'Submit Guess'}
              </button>
            </div>
          </div>
        )}

        {/* Completed state */}
        {phase === 'completed' && (
          <div className="mastermind-actions">
            <div className="mastermind-console">
              {game.winner_id === user?.id ? 'Victory!' : game.winner_id ? 'Defeated' : 'Draw!'}
            </div>
            <button className="btn btn-primary" onClick={() => navigate('/mastermind')}>
              Back to Games
            </button>
          </div>
        )}
      </main>
    </div>
  )
}
