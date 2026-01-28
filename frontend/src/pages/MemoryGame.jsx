import { useState, useEffect, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import Header from '../components/Header'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/api'

// const SHAPES = ['circle', 'triangle', 'square', 'diamond', 'star', 'hexagon', 'cross', 'heart']
// const COLORS = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#e67e22', '#1abc9c', '#e84393']

/*const getTileVisual = (id) => ({
  shape: SHAPES[id % SHAPES.length],
  color: COLORS[Math.floor(id / SHAPES.length) % COLORS.length],
})*/

const getTileVisual = (id) => ({
  emoji: EMOJIS[id % EMOJIS.length]
})

/* scg shapes
function TileShape({ shape, color, size = 24 }) {
  const s = size
  const half = s / 2
  switch (shape) {
    case 'circle':
      return <circle cx={half} cy={half} r={half * 0.7} fill={color} />
    case 'triangle':
      return <polygon points={`${half},${s * 0.1} ${s * 0.9},${s * 0.85} ${s * 0.1},${s * 0.85}`} fill={color} />
    case 'square':
      return <rect x={s * 0.15} y={s * 0.15} width={s * 0.7} height={s * 0.7} rx={2} fill={color} />
    case 'diamond':
      return <polygon points={`${half},${s * 0.05} ${s * 0.95},${half} ${half},${s * 0.95} ${s * 0.05},${half}`} fill={color} />
    case 'star': {
      const pts = []
      for (let i = 0; i < 5; i++) {
        const outerAngle = (i * 72 - 90) * Math.PI / 180
        const innerAngle = ((i * 72) + 36 - 90) * Math.PI / 180
        pts.push(`${half + half * 0.8 * Math.cos(outerAngle)},${half + half * 0.8 * Math.sin(outerAngle)}`)
        pts.push(`${half + half * 0.35 * Math.cos(innerAngle)},${half + half * 0.35 * Math.sin(innerAngle)}`)
      }
      return <polygon points={pts.join(' ')} fill={color} />
    }
    case 'hexagon': {
      const pts = []
      for (let i = 0; i < 6; i++) {
        const angle = (i * 60 - 30) * Math.PI / 180
        pts.push(`${half + half * 0.75 * Math.cos(angle)},${half + half * 0.75 * Math.sin(angle)}`)
      }
      return <polygon points={pts.join(' ')} fill={color} />
    }
    case 'cross':
      return (
        <g fill={color}>
          <rect x={s * 0.35} y={s * 0.1} width={s * 0.3} height={s * 0.8} rx={2} />
          <rect x={s * 0.1} y={s * 0.35} width={s * 0.8} height={s * 0.3} rx={2} />
        </g>
      )
    case 'heart': {
      const scale = s / 24
      return (
        <path
          d={`M${12 * scale},${20 * scale} C${4 * scale},${14 * scale} ${1 * scale},${8 * scale} ${5 * scale},${4.5 * scale} C${8 * scale},${2 * scale} ${12 * scale},${5 * scale} ${12 * scale},${5 * scale} C${12 * scale},${5 * scale} ${16 * scale},${2 * scale} ${19 * scale},${4.5 * scale} C${23 * scale},${8 * scale} ${20 * scale},${14 * scale} ${12 * scale},${20 * scale}Z`}
          fill={color}
        />
      )
    }
    default:
      return <circle cx={half} cy={half} r={half * 0.7} fill={color} />
  }
}*/
const EMOJIS = [
  '🐶', '🐱', '🦊', '🐻', '🐼', '🐸', '🦄', '🐵',
  '🐔', '🐧', '🐢', '🐙', '🦋', '🐞', '🐠', '🦀',
  '🍎', '🍌', '🍇', '🍓', '🍕', '🍩', '🍪', '🍉',
  '⚽', '🏀', '🎲', '🎮', '🚗', '✈️', '🚀', '⛵',
  '⭐', '🌙', '☀️', '🔥', '💎', '🎁', '🎈', '🎵'
]

export default function MemoryGame() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()

  const [gameData, setGameData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [moves, setMoves] = useState([])
  const [lastMove, setLastMove] = useState(null)

  // Selection state
  const [firstTile, setFirstTile] = useState(null) // {row, col}
  const [revealedTiles, setRevealedTiles] = useState(null) // {r1,c1,r2,c2,tile1,tile2,matched}
  const [isRevealing, setIsRevealing] = useState(false)
  const [showingLastMove, setShowingLastMove] = useState(false)
  const [lastShownMoveId, setLastShownMoveId] = useState(null)

  const loadGame = useCallback(async () => {
    try {
      const data = await api.getMemoryGame(id)
      setGameData(data)
      setMoves(data.moves)
      setLastMove(data.moves[0] || null)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadGame()
  }, [loadGame])

  const latestMoveId = gameData?.moves?.[0]?.id

  useEffect(() => {
    if (!gameData || !gameData.is_your_turn) return
    if (!latestMoveId) return

    const lastMove = gameData.moves[0]

    // Only show ONCE per move
    if (
      lastMove.id === lastShownMoveId ||     // already shown
      lastMove.user_id === user?.id ||       // was our move
      lastMove.matched                      // matched tiles stay up anyway
    ) return

    setLastShownMoveId(lastMove.id)
    setShowingLastMove(true)

    const timer = setTimeout(() => {
      setShowingLastMove(false)
    }, 2000)

    return () => clearTimeout(timer)

  }, [latestMoveId, gameData?.is_your_turn, user?.id])

  useEffect(() => {
    console.log("showingLastMove", showingLastMove)
  }, [showingLastMove])

  // Poll when waiting
  useEffect(() => {
    if (!gameData) return
    const game = gameData.game
    if (game.status === 'completed') return
    if (gameData.is_your_turn) return

    const interval = setInterval(loadGame, 5000)
    return () => clearInterval(interval)
  }, [gameData, loadGame])

  useEffect(() => {
    const handleVisibility = () => {
      if (document.visibilityState === 'visible') loadGame()
    }
    document.addEventListener('visibilitychange', handleVisibility)
    return () => document.removeEventListener('visibilitychange', handleVisibility)
  }, [loadGame])

  const getOpponent = () => {
    if (!gameData?.game || !user) return null
    return gameData.game.player1_id === user.id ? gameData.game.player2 : gameData.game.player1
  }

  const getMyScore = () => {
    if (!gameData?.game || !user) return 0
    return gameData.game.player1_id === user.id ? gameData.game.player1_score : gameData.game.player2_score
  }

  const getTheirScore = () => {
    if (!gameData?.game || !user) return 0
    return gameData.game.player1_id === user.id ? gameData.game.player2_score : gameData.game.player1_score
  }

  const handleTileClick = async (row, col) => {
    if (!gameData || !gameData.is_your_turn || isRevealing || showingLastMove) return
    if (gameData.game.status !== 'active') return

    // Can't click matched tiles
    if (gameData.board[row][col] !== -1) return

    // Can't click revealed tile
    if (revealedTiles) return

    if (!firstTile) {
      setFirstTile({ row, col })
      return
    }

    // Second tile selected
    if (firstTile.row === row && firstTile.col === col) return

    setIsRevealing(true)
    setError('')

    try {
      const result = await api.revealMemoryTiles(id, firstTile.row, firstTile.col, row, col)

      // Show both tiles temporarily
      setRevealedTiles({
        r1: firstTile.row, c1: firstTile.col,
        r2: row, c2: col,
        tile1: result.tile1, tile2: result.tile2,
        matched: result.matched,
      })

      if (result.matched) {
        setMessage('Match!')
      }

      // After a delay, clear and reload
      setTimeout(async () => {
        setFirstTile(null)
        setRevealedTiles(null)
        setIsRevealing(false)
        setMessage('')
        await loadGame()
      }, result.matched ? 800 : 1000)
      setShowingLastMove(false)
    } catch (err) {
      setError(err.message)
      setFirstTile(null)
      setRevealedTiles(null)
      setIsRevealing(false)
    }
  }

  const handleResign = async () => {
    if (!window.confirm('Resign?')) return
    try {
      await api.resignMemoryGame(id)
      await loadGame()
    } catch (err) {
      setError(err.message)
    }
  }

  const getTileContent = (row, col) => {
    if (!gameData) return null
    const boardVal = gameData.board[row][col]

    // Already matched
    if (boardVal !== -1) {
      return { tileId: boardVal, state: 'matched' }
    }

    // Currently being revealed (first selection) — show the tile face using full_board
    if (firstTile && firstTile.row === row && firstTile.col === col) {
      const fullBoard = gameData.full_board
      const tileId = fullBoard ? fullBoard[row][col] : null
      return { tileId, state: 'selected' }
    }

    // Just revealed via API response
    if (revealedTiles) {
      if (revealedTiles.r1 === row && revealedTiles.c1 === col) {
        return { tileId: revealedTiles.tile1, state: revealedTiles.matched ? 'just-matched' : 'revealed' }
      }
      if (revealedTiles.r2 === row && revealedTiles.c2 === col) {
        return { tileId: revealedTiles.tile2, state: revealedTiles.matched ? 'just-matched' : 'revealed' }
      }
    }

    // 3. --- NEW: Previewing the opponent's last move ---
    if (showingLastMove && gameData.moves.length > 0) {
      const lastMove = gameData.moves[0]
      const isFirst = lastMove.row1 === row && lastMove.col1 === col
      const isSecond = lastMove.row2 === row && lastMove.col2 === col

      if (isFirst || isSecond) {
        return {
          tileId: isFirst ? lastMove.tile1 : lastMove.tile2,
          state: 'revealed'
        }
      }
    }

    return { tileId: null, state: 'hidden' }
  }

  if (loading) {
    return (
      <div className="page memory-page">
        <Header />
        <main className="mem-container"><p className="mem-loading">Loading...</p></main>
      </div>
    )
  }

  if (!gameData?.game) {
    return (
      <div className="page memory-page">
        <Header />
        <main className="mem-container">
          <p className="mem-loading">Game not found</p>
          <button className="btn btn-primary" onClick={() => navigate('/memory')}>Back</button>
        </main>
      </div>
    )
  }

  const game = gameData.game
  const opponent = getOpponent()
  const myScore = getMyScore()
  const theirScore = getTheirScore()
  const board = gameData.board
  const rows = board.length
  const cols = board[0]?.length || 0

  return (
    <div className="page memory-page">
      <Header />
      <main className="mem-container">
        <div className="mem-header">
          <div className="mem-scores">
            <span className={`mem-player ${gameData.is_your_turn && game.status === 'active' ? 'active' : ''}`}>
              You: {myScore}
            </span>
            <span className="mem-divider">|</span>
            <span className={`mem-player ${!gameData.is_your_turn && game.status === 'active' ? 'active' : ''}`}>
              {opponent?.username}: {theirScore}
            </span>
          </div>
          <div className="mem-info">
            {game.status === 'completed' ? (
              <span className="mem-result">
                {game.winner_id === user?.id ? 'You Won' : game.winner_id ? 'You Lost' : 'Draw'}
              </span>
            ) : (
              <span>{gameData.is_your_turn ? 'Your turn' : `${opponent?.username}'s turn`}</span>
            )}
            <span className="mem-pairs">{gameData.matched_count}/{gameData.total_pairs} pairs</span>
          </div>
        </div>

        {error && <div className="alert alert-error">{error}</div>}
        {message && <div className="mem-message">{message}</div>}

        <div className="mem-board-wrapper">
          <div className="mem-grid" style={{ gridTemplateColumns: `repeat(${cols}, 1fr)` }}>
            {Array.from({ length: rows }).map((_, r) =>
              Array.from({ length: cols }).map((_, c) => {
                const content = getTileContent(r, c)
                const tileId = content?.tileId
                const state = content?.state || 'hidden'
                const visual = tileId != null ? getTileVisual(tileId) : null
                const isClickable = state === 'hidden' && gameData.is_your_turn && game.status === 'active' && !isRevealing && !revealedTiles && !showingLastMove;

                return (
                  <div
                    key={`${r}-${c}`}
                    className={`mem-tile ${state} ${isClickable ? 'clickable' : ''}`}
                    onClick={() => isClickable ? handleTileClick(r, c) : null}
                  >
                    {visual ? (
                      <>
                        <div className="mem-tile-face mem-emoji">
                          {visual.emoji}
                        </div>
                        {/*
                      <div className="mem-tile-face">
                        <svg viewBox="0 0 40 40" width="100%" height="100%">
                          <TileShape shape={visual.shape} color={visual.color} size={40} />
                        </svg>
                      </div>*/}</>
                    ) : state === 'selected' ? (
                      <div className="mem-tile-selected-dot" />
                    ) : (
                      <div className="mem-tile-back" />
                    )}
                  </div>
                )
              })
            )}
          </div>
        </div>

        <div className="mem-actions">
          {game.status === 'active' && (
            <button className="btn btn-secondary btn-small" onClick={handleResign}>Resign</button>
          )}
          {game.status === 'completed' && (
            <button className="btn btn-primary" onClick={() => navigate('/memory')}>Back to Games</button>
          )}
        </div>

        {gameData.moves.length > 0 && (
          <div className="mem-history">
            <h3 className="mem-history-title">Recent Moves</h3>
            <div className="mem-history-list">
              {gameData.moves.slice(0, 10).map((move) => {
                const isMe = move.user_id === user?.id
                const v1 = getTileVisual(move.tile1)
                const v2 = getTileVisual(move.tile2)
                return (
                  <div key={move.id} className={`mem-history-item ${move.matched ? 'match' : ''}`}>
                    <span className="mem-history-player">{isMe ? 'You' : opponent?.username}</span>
                    <div className="mem-history-tiles">
                      <span className="mem-history-emoji">{v1.emoji}</span>
                      <span className="mem-history-emoji">{v2.emoji}</span>
                      {/*
                      <svg viewBox="0 0 24 24" width="20" height="20">
                        <TileShape shape={v1.shape} color={v1.color} size={24} />
                      </svg>
                      <svg viewBox="0 0 24 24" width="20" height="20">
                        <TileShape shape={v2.shape} color={v2.color} size={24} />
                      </svg> */}
                    </div>
                    <span className="mem-history-result">{move.matched ? 'Match' : 'Miss'}</span>
                  </div>
                )
              })}
            </div>
          </div>
        )}

      </main>
    </div>
  )
}
