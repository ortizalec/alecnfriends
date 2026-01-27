import { useState, useEffect, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import Header from '../components/Header'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/api'

const ALL_COLORS = [
  { name: 'red', hex: '#c62828' },
  { name: 'orange', hex: '#ef6c00' },
  { name: 'yellow', hex: '#f9a825' },
  { name: 'green', hex: '#2e7d32' },
  { name: 'blue', hex: '#1565c0' },
  { name: 'purple', hex: '#6a1b9a' },
  { name: 'pink', hex: '#ad1457' },
  { name: 'brown', hex: '#4e342e' },
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

  // Setup/guess state
  const [currentCode, setCurrentCode] = useState([null, null, null, null])
  const [selectedSlot, setSelectedSlot] = useState(0)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [opponentExpanded, setOpponentExpanded] = useState(false)

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

  useEffect(() => {
    if (!game || phase === 'completed') return
    if (phase === 'active' && isYourTurn) return
    if (phase === 'setup' && !secretSet) return

    const interval = setInterval(loadGame, 5000)
    return () => clearInterval(interval)
  }, [game, phase, isYourTurn, secretSet, loadGame])

  useEffect(() => {
    const handleVisibility = () => {
      if (document.visibilityState === 'visible') loadGame()
    }
    document.addEventListener('visibilitychange', handleVisibility)
    return () => document.removeEventListener('visibilitychange', handleVisibility)
  }, [loadGame])

  const getOpponent = () => {
    if (!game || !user) return null
    return game.player1_id === user.id ? game.player2 : game.player1
  }

  const numColors = game?.num_colors || 6
  const allowRepeats = game?.allow_repeats !== false
  const colors = ALL_COLORS.slice(0, numColors)

  const handleColorSelect = (colorIndex) => {
    if (!allowRepeats && currentCode.includes(colorIndex) && currentCode[selectedSlot] !== colorIndex) {
      return // Can't use same color twice
    }
    const newCode = [...currentCode]
    newCode[selectedSlot] = colorIndex
    setCurrentCode(newCode)
    if (selectedSlot < CODE_LENGTH - 1) {
      setSelectedSlot(selectedSlot + 1)
    }
  }

  const handleSlotClick = (index) => {
    setSelectedSlot(index)
  }

  const handleClear = () => {
    setCurrentCode([null, null, null, null])
    setSelectedSlot(0)
  }

  const handleSubmitSecret = async () => {
    if (currentCode.some(c => c === null)) return
    setError('')
    setIsSubmitting(true)
    try {
      await api.setMastermindSecret(id, currentCode)
      setCurrentCode([null, null, null, null])
      setSelectedSlot(0)
      await loadGame()
      setMessage('Secret set!')
      setTimeout(() => setMessage(''), 2000)
    } catch (err) {
      setError(err.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleSubmitGuess = async () => {
    if (currentCode.some(c => c === null) || isSubmitting) return
    setError('')
    setIsSubmitting(true)
    try {
      const result = await api.makeMastermindGuess(id, currentCode)
      setCurrentCode([null, null, null, null])
      setSelectedSlot(0)
      const latestGuess = result.my_guesses[result.my_guesses.length - 1]
      if (latestGuess) {
        if (latestGuess.correct === CODE_LENGTH) {
          setMessage('Cracked!')
        } else {
          setMessage(`${latestGuess.correct} exact, ${latestGuess.misplaced} misplaced`)
        }
        setTimeout(() => setMessage(''), 2000)
      }
      await loadGame()
    } catch (err) {
      setError(err.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleResign = async () => {
    if (!window.confirm('Resign?')) return
    try {
      await api.resignMastermindGame(id)
      await loadGame()
    } catch (err) {
      setError(err.message)
    }
  }

  const Peg = ({ colorIndex, size = 'md', selected = false, onClick = null, disabled = false }) => {
    const color = colorIndex !== null ? colors[colorIndex] : null
    const sizeClass = size === 'sm' ? 'mm-peg-sm' : size === 'xs' ? 'mm-peg-xs' : 'mm-peg'
    return (
      <div
        className={`${sizeClass} ${selected ? 'selected' : ''} ${onClick && !disabled ? 'clickable' : ''} ${disabled ? 'disabled' : ''}`}
        style={{ backgroundColor: color ? color.hex : undefined }}
        onClick={onClick && !disabled ? onClick : undefined}
      />
    )
  }

  const Feedback = ({ correct, misplaced }) => (
    <div className="mm-feedback">
      {Array(correct).fill(0).map((_, i) => <div key={`c${i}`} className="fb-dot exact" />)}
      {Array(misplaced).fill(0).map((_, i) => <div key={`m${i}`} className="fb-dot partial" />)}
      {Array(CODE_LENGTH - correct - misplaced).fill(0).map((_, i) => <div key={`e${i}`} className="fb-dot" />)}
    </div>
  )

  const GuessRow = ({ guess }) => (
    <div className="mm-row">
      <span className="mm-row-num">{guess.guess_number}</span>
      <div className="mm-pegs">
        {guess.guess.map((c, i) => <Peg key={i} colorIndex={c} size="sm" />)}
      </div>
      <Feedback correct={guess.correct} misplaced={guess.misplaced} />
    </div>
  )

  if (loading) {
    return (
      <div className="page mastermind-page">
        <Header />
        <main className="mm-container"><p className="mm-loading">Loading...</p></main>
      </div>
    )
  }

  if (!game) {
    return (
      <div className="page mastermind-page">
        <Header />
        <main className="mm-container">
          <p className="mm-loading">Game not found</p>
          <button className="btn btn-primary" onClick={() => navigate('/mastermind')}>Back</button>
        </main>
      </div>
    )
  }

  const opponent = getOpponent()

  // Setup: Set secret
  if (phase === 'setup' && !secretSet) {
    return (
      <div className="page mastermind-page">
        <Header />
        <main className="mm-container">
          <div className="mm-header">
            <h2>Set Your Code</h2>
            <p className="mm-sub">{numColors} colors, {allowRepeats ? 'repeats OK' : 'no repeats'}</p>
          </div>

          {error && <div className="alert alert-error">{error}</div>}

          <div className="mm-input-area">
            <div className="mm-slots">
              {currentCode.map((c, i) => (
                <div key={i} className={`mm-slot ${selectedSlot === i ? 'selected' : ''}`} onClick={() => handleSlotClick(i)}>
                  <Peg colorIndex={c} selected={selectedSlot === i} />
                </div>
              ))}
            </div>

            <div className="mm-palette">
              {colors.map((_, i) => {
                const isUsed = !allowRepeats && currentCode.includes(i) && currentCode[selectedSlot] !== i
                return (
                  <button
                    key={i}
                    className={`mm-color-btn ${isUsed ? 'used' : ''}`}
                    style={{ backgroundColor: colors[i].hex }}
                    onClick={() => handleColorSelect(i)}
                    disabled={isUsed}
                  />
                )
              })}
            </div>

            <div className="mm-actions">
              <button className="btn btn-secondary btn-small" onClick={handleClear}>Clear</button>
              <button
                className="btn btn-primary"
                onClick={handleSubmitSecret}
                disabled={currentCode.some(c => c === null) || isSubmitting}
              >
                {isSubmitting ? '...' : 'Confirm'}
              </button>
            </div>
          </div>
        </main>
      </div>
    )
  }

  // Setup: Waiting
  if (phase === 'setup' && secretSet) {
    return (
      <div className="page mastermind-page">
        <Header />
        <main className="mm-container">
          <div className="mm-header">
            <h2>Waiting</h2>
            <p className="mm-sub">{opponent?.username} is setting their code...</p>
          </div>
          <div className="mm-your-secret">
            <span>Your code:</span>
            <div className="mm-pegs">{mySecret.map((c, i) => <Peg key={i} colorIndex={c} size="sm" />)}</div>
          </div>
        </main>
      </div>
    )
  }

  // Active / Completed
  return (
    <div className="page mastermind-page">
      <Header />
      <main className="mm-container">
        <div className="mm-header">
          <div className="mm-status">
            <span className={!isYourTurn && phase === 'active' ? 'active' : ''}>{opponent?.username}</span>
            <span className="vs">vs</span>
            <span className={isYourTurn && phase === 'active' ? 'active' : ''}>You</span>
          </div>
          {phase === 'completed' && (
            <div className="mm-result">
              {game.winner_id === user?.id ? 'You Won' : game.winner_id ? 'You Lost' : 'Draw'}
            </div>
          )}
          <div className="mm-info">Round {round}/{MAX_GUESSES} | {numColors}C{allowRepeats ? '' : ' NR'}</div>
        </div>

        {error && <div className="alert alert-error">{error}</div>}

        <div className="mm-boards">
          {/* Their guesses at your code */}
          <div className="mm-board mm-board-collapsible">
            <div className="mm-board-header" onClick={() => setOpponentExpanded(!opponentExpanded)} style={{ cursor: 'pointer' }}>
              <span>{opponent?.username}'s guesses {theirGuesses.length > 0 && `(${theirGuesses.length})`}</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <div className="mm-mini">{mySecret.map((c, i) => <Peg key={i} colorIndex={c} size="xs" />)}</div>
                <span className="mm-toggle">{opponentExpanded ? '▲' : '▼'}</span>
              </div>
            </div>
            {!opponentExpanded && theirGuesses.length > 0 && (
              <GuessRow guess={theirGuesses[theirGuesses.length - 1]} />
            )}
            {opponentExpanded && (
              <div className="mm-rows">
                {theirGuesses.map((g) => <GuessRow key={g.id} guess={g} />)}
                {theirGuesses.length === 0 && <div className="mm-empty">No guesses yet</div>}
              </div>
            )}
            {!opponentExpanded && theirGuesses.length === 0 && <div className="mm-empty">No guesses yet</div>}
          </div>

          {/* Your guesses */}
          <div className="mm-board">
            <div className="mm-board-header">
              <span>Your guesses</span>
              <div className="mm-mini">
                {phase === 'completed' && opponentSecret.length > 0
                  ? opponentSecret.map((c, i) => <Peg key={i} colorIndex={c} size="xs" />)
                  : Array(CODE_LENGTH).fill(0).map((_, i) => <div key={i} className="mm-peg-xs mystery">?</div>)
                }
              </div>
            </div>
            <div className="mm-rows">
              {myGuesses.map((g) => <GuessRow key={g.id} guess={g} />)}
              {myGuesses.length === 0 && <div className="mm-empty">No guesses yet</div>}
            </div>
          </div>
        </div>

        {phase === 'active' && (
          <div className="mm-input-area">
            <div className="mm-slots">
              {currentCode.map((c, i) => (
                <div key={i} className={`mm-slot ${selectedSlot === i ? 'selected' : ''}`} onClick={() => handleSlotClick(i)}>
                  <Peg colorIndex={c} selected={selectedSlot === i} />
                </div>
              ))}
            </div>

            <div className="mm-palette">
              {colors.map((_, i) => {
                const isUsed = !allowRepeats && currentCode.includes(i) && currentCode[selectedSlot] !== i
                return (
                  <button
                    key={i}
                    className={`mm-color-btn ${isUsed ? 'used' : ''}`}
                    style={{ backgroundColor: colors[i].hex }}
                    onClick={() => handleColorSelect(i)}
                    disabled={!isYourTurn || isUsed}
                  />
                )
              })}
            </div>

            <div className="mm-actions">
              <button className="btn btn-secondary btn-small" onClick={handleResign}>Resign</button>
              <span className="mm-msg">{message || (isYourTurn ? 'Your turn' : 'Waiting...')}</span>
              <button
                className="btn btn-primary"
                onClick={handleSubmitGuess}
                disabled={!isYourTurn || currentCode.some(c => c === null) || isSubmitting}
              >
                {isSubmitting ? '...' : 'Guess'}
              </button>
            </div>
          </div>
        )}

        {phase === 'completed' && (
          <div className="mm-actions">
            <button className="btn btn-primary" onClick={() => navigate('/mastermind')}>Back to Games</button>
          </div>
        )}
      </main>
    </div>
  )
}
