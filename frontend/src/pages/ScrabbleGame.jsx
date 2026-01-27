import { useState, useEffect, useCallback, useRef } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import Header from '../components/Header'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/api'

const BOARD_SIZE = 15

const BONUS_SQUARES = [
  [4, 0, 0, 1, 0, 0, 0, 4, 0, 0, 0, 1, 0, 0, 4],
  [0, 3, 0, 0, 0, 2, 0, 0, 0, 2, 0, 0, 0, 3, 0],
  [0, 0, 3, 0, 0, 0, 1, 0, 1, 0, 0, 0, 3, 0, 0],
  [1, 0, 0, 3, 0, 0, 0, 1, 0, 0, 0, 3, 0, 0, 1],
  [0, 0, 0, 0, 3, 0, 0, 0, 0, 0, 3, 0, 0, 0, 0],
  [0, 2, 0, 0, 0, 2, 0, 0, 0, 2, 0, 0, 0, 2, 0],
  [0, 0, 1, 0, 0, 0, 1, 0, 1, 0, 0, 0, 1, 0, 0],
  [4, 0, 0, 1, 0, 0, 0, 5, 0, 0, 0, 1, 0, 0, 4],
  [0, 0, 1, 0, 0, 0, 1, 0, 1, 0, 0, 0, 1, 0, 0],
  [0, 2, 0, 0, 0, 2, 0, 0, 0, 2, 0, 0, 0, 2, 0],
  [0, 0, 0, 0, 3, 0, 0, 0, 0, 0, 3, 0, 0, 0, 0],
  [1, 0, 0, 3, 0, 0, 0, 1, 0, 0, 0, 3, 0, 0, 1],
  [0, 0, 3, 0, 0, 0, 1, 0, 1, 0, 0, 0, 3, 0, 0],
  [0, 3, 0, 0, 0, 2, 0, 0, 0, 2, 0, 0, 0, 3, 0],
  [4, 0, 0, 1, 0, 0, 0, 4, 0, 0, 0, 1, 0, 0, 4],
]

const BONUS_LABELS = {
  1: '2L',
  2: '3L',
  3: '2W',
  4: '3W',
  5: '★',
}

const BONUS_CLASSES = {
  0: 'normal',
  1: 'double-letter',
  2: 'triple-letter',
  3: 'double-word',
  4: 'triple-word',
  5: 'center',
}

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')

const TILE_VALUES = {
  A: 1, B: 3, C: 3, D: 2, E: 1, F: 4, G: 2, H: 4, I: 1, J: 8, K: 5,
  L: 1, M: 3, N: 1, O: 1, P: 3, Q: 10, R: 1, S: 1, T: 1, U: 1, V: 4,
  W: 4, X: 8, Y: 4, Z: 10, ' ': 0
}

export default function ScrabbleGame() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()

  const [game, setGame] = useState(null)
  const [rack, setRack] = useState([])
  const [isYourTurn, setIsYourTurn] = useState(false)
  const [tilesRemaining, setTilesRemaining] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  const [selectedTile, setSelectedTile] = useState(null)
  const [placedTiles, setPlacedTiles] = useState([])
  const [preview, setPreview] = useState(null)
  const [lastMoveTiles, setLastMoveTiles] = useState([])

  const [showExchangeModal, setShowExchangeModal] = useState(false)
  const [exchangeSelection, setExchangeSelection] = useState([])

  const [showBlankModal, setShowBlankModal] = useState(false)
  const [pendingBlankTile, setPendingBlankTile] = useState(null)

  const [showMoreMenu, setShowMoreMenu] = useState(false)
  const [showTileBagModal, setShowTileBagModal] = useState(false)
  const [showHistoryModal, setShowHistoryModal] = useState(false)
  const [tileBagContents, setTileBagContents] = useState(null)
  const [gameHistory, setGameHistory] = useState(null)
  const moreMenuRef = useRef(null)

  const loadGame = useCallback(async () => {
    try {
      const data = await api.getScrabbleGame(id)
      setGame(data.game)
      setRack(data.rack)
      setIsYourTurn(data.is_your_turn)
      setTilesRemaining(data.tiles_remaining)
      setPlacedTiles([])
      setPreview(null)
      setSelectedTile(null)

      if (data.last_move?.tiles_played && data.last_move.move_type === 'play') {
        try {
          const tiles = JSON.parse(data.last_move.tiles_played)
          setLastMoveTiles(tiles.map(t => ({ row: t.row, col: t.col })))
        } catch {
          setLastMoveTiles([])
        }
      } else {
        setLastMoveTiles([])
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

  useEffect(() => {
    if (!game || game.status !== 'active' || isYourTurn) return

    const interval = setInterval(() => {
      loadGame()
    }, 8000)

    return () => clearInterval(interval)
  }, [game?.status, isYourTurn, loadGame])

  useEffect(() => {
    const handleVisibility = () => {
      if (document.visibilityState === 'visible') {
        loadGame()
      }
    }

    document.addEventListener('visibilitychange', handleVisibility)
    return () => document.removeEventListener('visibilitychange', handleVisibility)
  }, [loadGame])

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (moreMenuRef.current && !moreMenuRef.current.contains(e.target)) {
        setShowMoreMenu(false)
      }
    }
    if (showMoreMenu) {
      document.addEventListener('mousedown', handleClickOutside)
      return () => document.removeEventListener('mousedown', handleClickOutside)
    }
  }, [showMoreMenu])

  useEffect(() => {
    if (placedTiles.length === 0) {
      setPreview(null)
      return
    }

    const previewMove = async () => {
      try {
        const tiles = placedTiles.map(t => ({
          letter: t.displayLetter,
          row: t.row,
          col: t.col,
        }))
        const result = await api.previewScrabbleMove(id, tiles)
        setPreview(result)
      } catch {
        setPreview(null)
      }
    }

    const timeout = setTimeout(previewMove, 300)
    return () => clearTimeout(timeout)
  }, [placedTiles, id])

  const getTileValue = (letter) => TILE_VALUES[letter] || 0

  // Build tile map for connected component detection
  const buildTileMap = () => {
    const board = game?.board || []
    const allTiles = []

    for (let row = 0; row < BOARD_SIZE; row++) {
      for (let col = 0; col < BOARD_SIZE; col++) {
        if (board[row]?.[col]?.letter) {
          const isBlank = board[row][col].value === 0 && board[row][col].letter !== ' '
          const isLastMove = lastMoveTiles.some(t => t.row === row && t.col === col)
          allTiles.push({
            row, col,
            letter: board[row][col].letter,
            value: board[row][col].value,
            isNew: false,
            isBlank,
            isLastMove
          })
        }
      }
    }

    for (const tile of placedTiles) {
      allTiles.push({
        row: tile.row,
        col: tile.col,
        letter: tile.displayLetter,
        value: tile.isBlank ? 0 : getTileValue(tile.displayLetter),
        isNew: true,
        isBlank: tile.isBlank,
        isLastMove: false
      })
    }

    return allTiles
  }

  // Get tile neighbors for border rendering
  const getTileNeighbors = (row, col, tileSet) => ({
    top: tileSet.has(`${row - 1},${col}`),
    bottom: tileSet.has(`${row + 1},${col}`),
    left: tileSet.has(`${row},${col - 1}`),
    right: tileSet.has(`${row},${col + 1}`),
  })

  const handleCellClick = (row, col) => {
    if (!isYourTurn || game?.status !== 'active') return

    const board = game?.board || []
    if (board[row]?.[col]?.letter) return

    const existingPlaced = placedTiles.find(t => t.row === row && t.col === col)
    if (existingPlaced) {
      setPlacedTiles(prev => prev.filter(t => !(t.row === row && t.col === col)))
      return
    }

    if (selectedTile) {
      setPlacedTiles(prev => [...prev, {
        letter: selectedTile.letter,
        displayLetter: selectedTile.isBlank ? selectedTile.chosenLetter : selectedTile.letter,
        row,
        col,
        rackIndex: selectedTile.rackIndex,
        isBlank: selectedTile.isBlank,
      }])
      setSelectedTile(null)
    }
  }

  const getAvailableRackTiles = () => {
    const usedIndices = new Set(placedTiles.map(t => t.rackIndex))
    return rack
      .map((tile, idx) => ({
        ...tile,
        originalIndex: idx,
      }))
      .filter(t => !usedIndices.has(t.originalIndex))
  }

  const handleRackTileClick = (availableIdx) => {
    const availableTiles = getAvailableRackTiles()
    const tile = availableTiles[availableIdx]

    if (!tile) return

    if (selectedTile && selectedTile.rackIndex === tile.originalIndex) {
      setSelectedTile(null)
      return
    }

    if (tile.letter === ' ') {
      setPendingBlankTile({ rackIndex: tile.originalIndex, availableIdx })
      setShowBlankModal(true)
      return
    }

    setSelectedTile({
      rackIndex: tile.originalIndex,
      letter: tile.letter,
      chosenLetter: null,
      isBlank: false,
    })
  }

  const handleBlankLetterSelect = (letter) => {
    setShowBlankModal(false)

    setSelectedTile({
      rackIndex: pendingBlankTile.rackIndex,
      letter: ' ',
      chosenLetter: letter,
      isBlank: true,
    })

    setPendingBlankTile(null)
  }

  const handleSubmit = async () => {
    if (placedTiles.length === 0) return
    setError('')
    setMessage('')

    try {
      const tiles = placedTiles.map(t => ({
        letter: t.displayLetter,
        row: t.row,
        col: t.col,
      }))
      const scoreToShow = preview?.score || 0
      await api.playScrabbleMove(id, tiles)
      await loadGame()
      setMessage(`+${scoreToShow} points!`)
      setTimeout(() => setMessage(''), 3000)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleClear = () => {
    setPlacedTiles([])
    setSelectedTile(null)
    setPreview(null)
  }

  const handlePass = async () => {
    if (!window.confirm('Pass your turn?')) return
    setError('')
    setShowMoreMenu(false)

    try {
      const result = await api.passScrabbleTurn(id)
      setGame(result.game)
      setRack(result.rack)
      setIsYourTurn(result.is_your_turn)
      setTilesRemaining(result.tiles_remaining)
      setPlacedTiles([])
      setSelectedTile(null)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleExchange = async () => {
    if (exchangeSelection.length === 0) return
    setError('')

    try {
      const tiles = exchangeSelection.map(idx => rack[idx].letter)
      const result = await api.exchangeScrabbleTiles(id, tiles)
      setGame(result.game)
      setRack(result.rack)
      setIsYourTurn(result.is_your_turn)
      setTilesRemaining(result.tiles_remaining)
      setShowExchangeModal(false)
      setExchangeSelection([])
      setPlacedTiles([])
      setSelectedTile(null)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleResign = async () => {
    if (!window.confirm('Resign this game?')) return
    setError('')
    setShowMoreMenu(false)

    try {
      const result = await api.resignScrabbleGame(id)
      setGame(result.game)
      setIsYourTurn(false)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleShuffle = () => {
    const usedIndices = new Set(placedTiles.map(t => t.rackIndex))
    const newRack = [...rack]

    const availableIndices = newRack
      .map((_, idx) => idx)
      .filter(idx => !usedIndices.has(idx))

    for (let i = availableIndices.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1))
      const idxI = availableIndices[i]
      const idxJ = availableIndices[j]
      ;[newRack[idxI], newRack[idxJ]] = [newRack[idxJ], newRack[idxI]]
    }

    setRack(newRack)
    setSelectedTile(null)
  }

  const handleOpenTileBag = async () => {
    setShowMoreMenu(false)
    try {
      const data = await api.getTileBag(id)
      setTileBagContents(data)
      setShowTileBagModal(true)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleOpenHistory = async () => {
    setShowMoreMenu(false)
    try {
      const data = await api.getGameHistory(id)
      setGameHistory(data.history)
      setShowHistoryModal(true)
    } catch (err) {
      setError(err.message)
    }
  }

  const toggleExchangeTile = (idx) => {
    setExchangeSelection(prev =>
      prev.includes(idx) ? prev.filter(i => i !== idx) : [...prev, idx]
    )
  }

  const getOpponent = () => {
    if (!game || !user) return null
    return game.player1_id === user.id ? game.player2 : game.player1
  }

  const truncateName = (name, maxLen = 12) => {
    if (!name) return ''
    return name.length > maxLen ? name.slice(0, maxLen - 1) + '…' : name
  }

  const getScores = () => {
    if (!game || !user) return { you: 0, them: 0 }
    const isPlayer1 = game.player1_id === user.id
    return {
      you: isPlayer1 ? game.player1_score : game.player2_score,
      them: isPlayer1 ? game.player2_score : game.player1_score,
    }
  }

  if (loading) {
    return (
      <div className="page scrabble-page">
        <Header />
        <main className="container main-content">
          <p>Loading...</p>
        </main>
      </div>
    )
  }

  if (!game) {
    return (
      <div className="page scrabble-page">
        <Header />
        <main className="container main-content">
          <p>Game not found</p>
          <button className="btn btn-primary mt-2" onClick={() => navigate('/')}>
            Back to Games
          </button>
        </main>
      </div>
    )
  }

  const opponent = getOpponent()
  const scores = getScores()
  const availableTiles = getAvailableRackTiles()
  const board = game?.board || []

  // Build tile data for rendering
  const allTiles = buildTileMap()
  const tileSet = new Set(allTiles.map(t => `${t.row},${t.col}`))
  const tileMap = new Map(allTiles.map(t => [`${t.row},${t.col}`, t]))

  const getEndStatusText = () => {
    if (game.status !== 'active') {
      return game.winner_id === user?.id ? 'You Won!' : game.winner_id ? 'You Lost' : 'Draw'
    }
    return null
  }

  // Get CSS classes for a cell
  const getCellClass = (row, col) => {
    const bonus = BONUS_SQUARES[row][col]
    const tileKey = `${row},${col}`
    const tile = tileMap.get(tileKey)
    const hasTile = !!tile

    const classes = ['scrabble-cell', BONUS_CLASSES[bonus]]

    if (!hasTile && isYourTurn && game.status === 'active') {
      classes.push('clickable')
    }

    return classes.join(' ')
  }

  // Get CSS classes for a tile
  const getTileClass = (tile, neighbors) => {
    const classes = ['scrabble-tile']

    if (tile.isNew) classes.push('new-tile')
    else if (tile.isLastMove) classes.push('last-move-tile')

    // Add neighbor classes for connected rendering
    if (neighbors.top) classes.push('connected-top')
    if (neighbors.bottom) classes.push('connected-bottom')
    if (neighbors.left) classes.push('connected-left')
    if (neighbors.right) classes.push('connected-right')

    return classes.join(' ')
  }

  // Get play button text based on game state
  const getPlayButtonText = () => {
    if (game.status !== 'active') {
      return game.winner_id === user?.id ? 'Won!' : game.winner_id ? 'Lost' : 'Draw'
    }
    if (!isYourTurn) return 'Waiting'
    if (preview?.valid) return `Play +${preview.score}`
    return 'Play'
  }

  const isPlayDisabled = () => {
    if (game.status !== 'active') return true
    if (!isYourTurn) return true
    return !preview?.valid
  }

  return (
    <div className="page scrabble-page">
      <Header />
      <main className="scrabble-container">
        {error && <div className="alert alert-error">{error}</div>}

        {/* Board */}
        <div className="scrabble-board-container">
          <div className="scrabble-board-wrapper">
            <div className="scrabble-board">
              {Array.from({ length: BOARD_SIZE }).map((_, row) => (
                <div key={row} className="scrabble-row">
                  {Array.from({ length: BOARD_SIZE }).map((_, col) => {
                    const tileKey = `${row},${col}`
                    const tile = tileMap.get(tileKey)
                    const bonus = BONUS_SQUARES[row][col]
                    const neighbors = getTileNeighbors(row, col, tileSet)

                    return (
                      <div
                        key={col}
                        className={getCellClass(row, col)}
                        onClick={() => handleCellClick(row, col)}
                      >
                        {/* Bonus label (only if no tile) */}
                        {!tile && bonus > 0 && (
                          <span className="bonus-label">{BONUS_LABELS[bonus]}</span>
                        )}

                        {/* Tile */}
                        {tile && (
                          <div className={getTileClass(tile, neighbors)}>
                            <span className="tile-letter">
                              {tile.letter === ' ' ? '' : tile.letter}
                            </span>
                            {tile.value > 0 && (
                              <span className="tile-points">{tile.value}</span>
                            )}
                            {tile.isBlank && tile.letter !== ' ' && (
                              <span className="blank-underline" />
                            )}
                          </div>
                        )}
                      </div>
                    )
                  })}
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Rack */}
        <div className="scrabble-rack">
          {availableTiles.map((tile, idx) => {
            const isBlank = tile.letter === ' '
            const isSelected = selectedTile && selectedTile.rackIndex === tile.originalIndex
            const displayLetter = isSelected && selectedTile.isBlank ? selectedTile.chosenLetter : (isBlank ? '' : tile.letter)
            return (
              <button
                key={`${tile.originalIndex}-${tile.letter}`}
                className={`rack-tile ${isSelected ? 'selected' : ''} ${isBlank ? 'blank' : ''}`}
                onClick={() => handleRackTileClick(idx)}
                disabled={!isYourTurn || game.status !== 'active'}
              >
                <span className="tile-letter">
                  {displayLetter || ''}
                </span>
                <span className="tile-value">{tile.value}</span>
                {isBlank && !isSelected && (
                  <span className="blank-indicator">?</span>
                )}
              </button>
            )
          })}
        </div>

        {/* Unified Action Bar */}
        <div className="scrabble-actions">
          <div className="actions-left">
            <div className="dropup-container" ref={moreMenuRef}>
              <button
                className="btn btn-secondary btn-icon"
                onClick={() => setShowMoreMenu(!showMoreMenu)}
              >
                ···
              </button>
              {showMoreMenu && (
                <div className="dropup-menu">
                  {game.status === 'active' && isYourTurn && (
                    <>
                      <button onClick={handlePass}>Pass</button>
                      <button onClick={handleResign} className="danger">Resign</button>
                    </>
                  )}
                  <button onClick={handleOpenTileBag}>Tile Bag</button>
                  <button onClick={handleOpenHistory}>History</button>
                </div>
              )}
            </div>
            {game.status === 'active' && isYourTurn && (
              <>
                <button
                  className="btn btn-secondary btn-icon"
                  onClick={() => {
                    setShowExchangeModal(true)
                    setExchangeSelection([])
                  }}
                  title="Swap tiles"
                >
                  ⇄
                </button>
                <button className="btn btn-secondary btn-icon" onClick={handleShuffle} title="Shuffle rack">
                  ↻
                </button>
              </>
            )}
          </div>

          <div className="actions-scores">
            <span className={`score-you ${isYourTurn ? 'active' : ''}`}>{scores.you}</span>
            <span className="score-bag">{tilesRemaining}</span>
            <span className={`score-them ${!isYourTurn && game.status === 'active' ? 'active' : ''}`}>{scores.them}</span>
          </div>

          <button
            className={`btn btn-play ${isPlayDisabled() ? 'btn-disabled' : 'btn-primary'}`}
            onClick={handleSubmit}
            disabled={isPlayDisabled()}
          >
            {getPlayButtonText()}
          </button>
        </div>

        {/* Blank Tile Letter Picker Modal */}
        {showBlankModal && (
          <>
            <div className="modal-overlay" onClick={() => setShowBlankModal(false)} />
            <div className="modal blank-modal">
              <h2 className="modal-title">Choose a Letter</h2>
              <div className="letter-grid">
                {LETTERS.map(letter => (
                  <button
                    key={letter}
                    className="letter-btn"
                    onClick={() => handleBlankLetterSelect(letter)}
                  >
                    {letter}
                  </button>
                ))}
              </div>
              <button
                className="btn btn-secondary btn-full mt-2"
                onClick={() => setShowBlankModal(false)}
              >
                Cancel
              </button>
            </div>
          </>
        )}

        {/* Exchange Modal */}
        {showExchangeModal && (
          <>
            <div className="modal-overlay" onClick={() => setShowExchangeModal(false)} />
            <div className="modal">
              <h2 className="modal-title">Exchange Tiles</h2>
              <p className="mb-2">Select tiles to exchange:</p>
              <div className="exchange-tiles">
                {rack.map((tile, idx) => (
                  <button
                    key={idx}
                    className={`rack-tile ${exchangeSelection.includes(idx) ? 'selected' : ''}`}
                    onClick={() => toggleExchangeTile(idx)}
                  >
                    <span className="tile-letter">{tile.letter === ' ' ? '?' : tile.letter}</span>
                    <span className="tile-value">{tile.value}</span>
                  </button>
                ))}
              </div>
              <div className="modal-actions">
                <button
                  className="btn btn-primary"
                  onClick={handleExchange}
                  disabled={exchangeSelection.length === 0}
                >
                  Exchange ({exchangeSelection.length})
                </button>
                <button
                  className="btn btn-secondary"
                  onClick={() => setShowExchangeModal(false)}
                >
                  Cancel
                </button>
              </div>
            </div>
          </>
        )}

        {/* Tile Bag Modal */}
        {showTileBagModal && tileBagContents && (
          <>
            <div className="modal-overlay" onClick={() => setShowTileBagModal(false)} />
            <div className="modal tile-bag-modal">
              <h2 className="modal-title">Tile Bag ({tileBagContents.total} tiles)</h2>
              <div className="tile-bag-grid">
                {Object.entries(tileBagContents.tiles)
                  .sort(([a], [b]) => a.localeCompare(b))
                  .map(([letter, count]) => (
                    <div key={letter} className="tile-bag-item">
                      <span className="tile-bag-letter">{letter}</span>
                      <span className="tile-bag-count">{count}</span>
                    </div>
                  ))}
              </div>
              <button
                className="btn btn-secondary btn-full mt-2"
                onClick={() => setShowTileBagModal(false)}
              >
                Close
              </button>
            </div>
          </>
        )}

        {/* History Modal */}
        {showHistoryModal && gameHistory && (
          <>
            <div className="modal-overlay" onClick={() => setShowHistoryModal(false)} />
            <div className="modal history-modal">
              <h2 className="modal-title">Game History</h2>
              <div className="history-list">
                {gameHistory.length === 0 ? (
                  <p className="text-muted">No moves yet</p>
                ) : (
                  gameHistory.map((move, idx) => (
                    <div key={idx} className="history-item">
                      <div className="history-move-number">{move.move_number}</div>
                      <div className="history-details">
                        <span className="history-player">{move.player_name}</span>
                        <span className="history-type">
                          {move.move_type === 'play' ? (
                            <>played {move.words_formed?.join(', ')}</>
                          ) : move.move_type === 'pass' ? (
                            'passed'
                          ) : move.move_type === 'exchange' ? (
                            'exchanged tiles'
                          ) : (
                            'resigned'
                          )}
                        </span>
                      </div>
                      {move.score > 0 && (
                        <div className="history-score">+{move.score}</div>
                      )}
                    </div>
                  ))
                )}
              </div>
              <button
                className="btn btn-secondary btn-full mt-2"
                onClick={() => setShowHistoryModal(false)}
              >
                Close
              </button>
            </div>
          </>
        )}
      </main>
    </div>
  )
}
