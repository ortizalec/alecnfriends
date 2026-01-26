import { useState, useEffect, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import Header from '../components/Header'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/api'

const GRID_SIZE = 10
const SHIP_TYPES = [
  { type: 'carrier', size: 5, name: 'Carrier' },
  { type: 'battleship', size: 4, name: 'Battleship' },
  { type: 'cruiser', size: 3, name: 'Cruiser' },
  { type: 'submarine', size: 3, name: 'Submarine' },
  { type: 'destroyer', size: 2, name: 'Destroyer' },
]

export default function BattleshipGame() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()

  const [game, setGame] = useState(null)
  const [myBoard, setMyBoard] = useState([])
  const [enemyBoard, setEnemyBoard] = useState([])
  const [myShips, setMyShips] = useState([])
  const [isYourTurn, setIsYourTurn] = useState(false)
  const [shipsReady, setShipsReady] = useState(false)
  const [phase, setPhase] = useState('setup')
  const [enemyShipsRemaining, setEnemyShipsRemaining] = useState(5)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  // Setup phase state
  const [placingShip, setPlacingShip] = useState(null) // { type, size, name }
  const [isHorizontal, setIsHorizontal] = useState(true)
  const [placedShips, setPlacedShips] = useState([])
  const [hoverCells, setHoverCells] = useState([])

  // Firing state
  const [selectedTarget, setSelectedTarget] = useState(null) // { row, col }
  const [isFiring, setIsFiring] = useState(false)

  const loadGame = useCallback(async () => {
    try {
      const data = await api.getBattleshipGame(id)
      setGame(data.game)
      setMyBoard(data.my_board)
      setEnemyBoard(data.enemy_board)
      setMyShips(data.my_ships)
      setIsYourTurn(data.is_your_turn)
      setShipsReady(data.ships_ready)
      setPhase(data.phase)
      setEnemyShipsRemaining(data.enemy_ships_remaining)

      if (data.ships_ready && data.my_ships.length > 0) {
        setPlacedShips(data.my_ships)
      }
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
    if (phase === 'setup' && !shipsReady) return

    const interval = setInterval(() => {
      loadGame()
    }, 5000)

    return () => clearInterval(interval)
  }, [game, phase, isYourTurn, shipsReady, loadGame])

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

  const getShipCells = (ship) => {
    const cells = []
    for (let i = 0; i < ship.size; i++) {
      if (ship.horizontal) {
        cells.push({ row: ship.start_row, col: ship.start_col + i })
      } else {
        cells.push({ row: ship.start_row + i, col: ship.start_col })
      }
    }
    return cells
  }

  const isValidPlacement = (startRow, startCol, size, horizontal) => {
    const cells = []
    for (let i = 0; i < size; i++) {
      const row = horizontal ? startRow : startRow + i
      const col = horizontal ? startCol + i : startCol
      if (row < 0 || row >= GRID_SIZE || col < 0 || col >= GRID_SIZE) {
        return { valid: false, cells: [] }
      }
      cells.push({ row, col })
    }

    // Check overlap with already placed ships
    for (const ship of placedShips) {
      const shipCells = getShipCells(ship)
      for (const cell of cells) {
        if (shipCells.some(sc => sc.row === cell.row && sc.col === cell.col)) {
          return { valid: false, cells }
        }
      }
    }

    return { valid: true, cells }
  }

  const handleSetupCellHover = (row, col) => {
    if (!placingShip) {
      setHoverCells([])
      return
    }

    const { valid, cells } = isValidPlacement(row, col, placingShip.size, isHorizontal)
    setHoverCells(cells.map(c => ({ ...c, valid })))
  }

  const handleSetupCellClick = (row, col) => {
    if (!placingShip) return

    const { valid, cells } = isValidPlacement(row, col, placingShip.size, isHorizontal)
    if (!valid) return

    const newShip = {
      type: placingShip.type,
      size: placingShip.size,
      start_row: row,
      start_col: col,
      horizontal: isHorizontal,
    }

    setPlacedShips([...placedShips, newShip])
    setPlacingShip(null)
    setHoverCells([])
  }

  const handleRemoveShip = (shipType) => {
    setPlacedShips(placedShips.filter(s => s.type !== shipType))
  }

  const handleSubmitShips = async () => {
    if (placedShips.length !== SHIP_TYPES.length) return

    setError('')
    try {
      await api.placeBattleshipShips(id, placedShips)
      await loadGame()
      setMessage('Ships placed! Waiting for opponent...')
      setTimeout(() => setMessage(''), 3000)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleEnemyCellClick = (row, col) => {
    if (phase !== 'active' || !isYourTurn) return
    if (enemyBoard[row]?.[col] !== 'empty') return

    setSelectedTarget({ row, col })
  }

  const handleFire = async () => {
    if (!selectedTarget || isFiring) return

    setError('')
    setIsFiring(true)

    // Wait for bomb animation to complete
    await new Promise(resolve => setTimeout(resolve, 600))

    try {
      const result = await api.fireBattleshipShot(id, selectedTarget.row, selectedTarget.col)

      if (result.hit) {
        if (result.sunk) {
          setMessage(`Hit! You sunk their ${result.ship_type}!`)
        } else {
          setMessage('Hit!')
        }
      } else {
        setMessage('Miss!')
      }

      if (result.game_over) {
        setMessage(`Game Over! ${result.winner} wins!`)
      }

      setSelectedTarget(null)
      setIsFiring(false)
      await loadGame()
      setTimeout(() => setMessage(''), 3000)
    } catch (err) {
      setError(err.message)
      setIsFiring(false)
    }
  }

  const handleResign = async () => {
    if (!window.confirm('Resign this game?')) return
    setError('')

    try {
      await api.resignBattleshipGame(id)
      await loadGame()
    } catch (err) {
      setError(err.message)
    }
  }

  const getCellClass = (board, row, col, isEnemy = false) => {
    const cell = board[row]?.[col]
    const classes = ['battleship-cell']

    if (cell === 'hit') classes.push('hit')
    else if (cell === 'miss') classes.push('miss')
    else if (cell === 'ship' && !isEnemy) classes.push('ship')

    if (isEnemy && selectedTarget?.row === row && selectedTarget?.col === col) {
      classes.push('targeted')
      if (isFiring) classes.push('firing')
    }

    return classes.join(' ')
  }

  const getSetupCellClass = (row, col) => {
    const classes = ['battleship-cell']

    // Check if this cell is part of a placed ship
    for (const ship of placedShips) {
      const cells = getShipCells(ship)
      if (cells.some(c => c.row === row && c.col === col)) {
        classes.push('ship')
        break
      }
    }

    // Check hover
    const hoverCell = hoverCells.find(c => c.row === row && c.col === col)
    if (hoverCell) {
      classes.push(hoverCell.valid ? 'hover-valid' : 'hover-invalid')
    }

    return classes.join(' ')
  }

  const getUnplacedShips = () => {
    const placedTypes = new Set(placedShips.map(s => s.type))
    return SHIP_TYPES.filter(s => !placedTypes.has(s.type))
  }

  const countSunkShips = (ships) => {
    return ships.filter(s => s.hits >= s.size).length
  }

  if (loading) {
    return (
      <div className="page battleship-page">
        <Header />
        <main className="container main-content">
          <p>Loading...</p>
        </main>
      </div>
    )
  }

  if (!game) {
    return (
      <div className="page battleship-page">
        <Header />
        <main className="container main-content">
          <p>Game not found</p>
          <button className="btn btn-primary mt-2" onClick={() => navigate('/battleship')}>
            Back to Games
          </button>
        </main>
      </div>
    )
  }

  const opponent = getOpponent()

  // Setup Phase
  if (phase === 'setup' && !shipsReady) {
    return (
      <div className="page battleship-page">
        <Header />
        <main className="battleship-container">
          <div className="battleship-header">
            <h2>Place Your Ships</h2>
            <p className="text-muted">Tap a ship, then tap the grid to place it</p>
          </div>

          {error && <div className="alert alert-error">{error}</div>}

          <div className="battleship-setup-grid">
            <div className="grid-label">Your Board</div>
            <div
              className="battleship-grid my-grid"
              onMouseLeave={() => setHoverCells([])}
            >
              {Array.from({ length: GRID_SIZE }).map((_, row) => (
                <div key={row} className="battleship-row">
                  {Array.from({ length: GRID_SIZE }).map((_, col) => (
                    <div
                      key={col}
                      className={getSetupCellClass(row, col)}
                      onMouseEnter={() => handleSetupCellHover(row, col)}
                      onClick={() => handleSetupCellClick(row, col)}
                    />
                  ))}
                </div>
              ))}
            </div>
          </div>

          <div className="battleship-ship-selector">
            <div className="ship-selector-header">
              <span>Ships to Place</span>
              <button
                className="btn btn-secondary btn-small"
                onClick={() => setIsHorizontal(!isHorizontal)}
              >
                {isHorizontal ? 'Horizontal' : 'Vertical'}
              </button>
            </div>
            <div className="ship-list">
              {getUnplacedShips().map((ship) => (
                <button
                  key={ship.type}
                  className={`ship-btn ${placingShip?.type === ship.type ? 'selected' : ''}`}
                  onClick={() => setPlacingShip(ship)}
                >
                  <span className="ship-name">{ship.name}</span>
                  <span className="ship-size">
                    {Array.from({ length: ship.size }).map((_, i) => (
                      <span key={i} className="ship-dot" />
                    ))}
                  </span>
                </button>
              ))}
            </div>
            {placedShips.length > 0 && (
              <div className="placed-ships">
                <span className="placed-label">Placed:</span>
                {placedShips.map((ship) => (
                  <button
                    key={ship.type}
                    className="placed-ship-btn"
                    onClick={() => handleRemoveShip(ship.type)}
                  >
                    {SHIP_TYPES.find(s => s.type === ship.type)?.name} ×
                  </button>
                ))}
              </div>
            )}
          </div>

          <div className="battleship-actions">
            <button
              className="btn btn-primary btn-play"
              onClick={handleSubmitShips}
              disabled={placedShips.length !== SHIP_TYPES.length}
            >
              Ready ({placedShips.length}/5)
            </button>
          </div>
        </main>
      </div>
    )
  }

  // Waiting for opponent to place ships
  if (phase === 'setup' && shipsReady) {
    return (
      <div className="page battleship-page">
        <Header />
        <main className="battleship-container">
          <div className="battleship-header">
            <h2>Waiting for Opponent</h2>
            <p className="text-muted">{opponent?.username} is placing their ships...</p>
          </div>

          <div className="battleship-setup-grid">
            <div className="grid-label">Your Board</div>
            <div className="battleship-grid my-grid">
              {Array.from({ length: GRID_SIZE }).map((_, row) => (
                <div key={row} className="battleship-row">
                  {Array.from({ length: GRID_SIZE }).map((_, col) => (
                    <div
                      key={col}
                      className={getCellClass(myBoard, row, col, false)}
                    />
                  ))}
                </div>
              ))}
            </div>
          </div>
        </main>
      </div>
    )
  }

  // Active/Completed game
  return (
    <div className="page battleship-page">
      <Header />
      <main className="battleship-container">
        {/* Header bar */}
        <div className="battleship-header">
          <div className="battleship-scores">
            <span className={`player-status ${isYourTurn && phase === 'active' ? 'current-turn' : ''}`}>
              You: {myShips.length - countSunkShips(myShips)} ships
            </span>
            <span className="status-divider">vs</span>
            <span className={`player-status ${!isYourTurn && phase === 'active' ? 'current-turn' : ''}`}>
              {opponent?.username}: {enemyShipsRemaining} ships
            </span>
            {phase === 'completed' && (
              <span className="game-result-inline">
                {game.winner_id === user?.id ? 'You Won!' : 'You Lost'}
              </span>
            )}
          </div>
        </div>

        {error && <div className="alert alert-error">{error}</div>}

        {/* Enemy board (top) */}
        <div className="battleship-grids">
          <div className="grid-section enemy-section">
            <div className="grid-label">Enemy Waters</div>
            <div className="battleship-grid enemy-grid">
              {Array.from({ length: GRID_SIZE }).map((_, row) => (
                <div key={row} className="battleship-row">
                  {Array.from({ length: GRID_SIZE }).map((_, col) => (
                    <div
                      key={col}
                      className={getCellClass(enemyBoard, row, col, true)}
                      onClick={() => handleEnemyCellClick(row, col)}
                    />
                  ))}
                </div>
              ))}
            </div>
          </div>

          {/* My board (bottom) */}
          <div className="grid-section my-section">
            <div className="grid-label">Your Fleet</div>
            <div className="battleship-grid my-grid">
              {Array.from({ length: GRID_SIZE }).map((_, row) => (
                <div key={row} className="battleship-row">
                  {Array.from({ length: GRID_SIZE }).map((_, col) => (
                    <div
                      key={col}
                      className={getCellClass(myBoard, row, col, false)}
                    />
                  ))}
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Actions bar */}
        {phase === 'active' && (
          <div className="battleship-actions">
            <button className="btn btn-secondary" onClick={handleResign}>
              Resign
            </button>
            <div className="battleship-console">
              {message || (isYourTurn ? 'Select a target' : 'Waiting for opponent...')}
            </div>
            <button
              className="btn btn-primary btn-play"
              onClick={handleFire}
              disabled={!isYourTurn || !selectedTarget || isFiring}
            >
              {isFiring ? 'Firing...' : 'Fire!'}
            </button>
          </div>
        )}

        {phase === 'completed' && (
          <div className="battleship-actions">
            <div className="battleship-console">
              {game.winner_id === user?.id ? 'Victory!' : 'Defeated'}
            </div>
            <button className="btn btn-primary" onClick={() => navigate('/battleship')}>
              Back to Games
            </button>
          </div>
        )}
      </main>
    </div>
  )
}
