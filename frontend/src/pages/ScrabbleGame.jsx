import { useState, useEffect, useRef, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import Header from '../components/Header'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/api'

const BOARD_SIZE = 15
const TILE_SIZE = 48
const BOARD_PADDING = 4

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

// NYT Games color palette
const COLORS = {
  boardBg: '#f3f3f3',      // light gray board background
  gridLine: '#e8e8e8',     // subtle grid lines
  normal: '#ffffff',       // empty squares - white
  doubleLetter: '#b8d4e8', // soft blue
  tripleLetter: '#8b7bb5', // muted purple
  doubleWord: '#e8c4c4',   // soft pink
  tripleWord: '#d4a843',   // gold/mustard
  center: '#f3f3f3',       // center square
  tile: '#f5f0e1',         // placed tiles - cream/beige
  tileShadow: '#d4cfc4',   // tile shadow
  tileBorder: '#c4bfb4',   // tile border
  newTile: '#c8e6c9',      // newly placed tiles - soft green
  newTileBorder: '#81c784',
  lastMoveTile: '#c8e6c9', // last move tiles - soft green
  lastMoveBorder: '#81c784',
  text: '#1a1a1a',         // dark text
  textMuted: '#666666',    // muted text
}

const BONUS_COLORS = {
  0: COLORS.normal,
  1: COLORS.doubleLetter,
  2: COLORS.tripleLetter,
  3: COLORS.doubleWord,
  4: COLORS.tripleWord,
  5: COLORS.center,
}

const BONUS_LABELS = {
  1: '2L',
  2: '3L',
  3: '2W',
  4: '3W',
  5: '★',
}

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')

export default function ScrabbleGame() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()
  const canvasRef = useRef(null)

  const [game, setGame] = useState(null)
  const [rack, setRack] = useState([])
  const [isYourTurn, setIsYourTurn] = useState(false)
  const [tilesRemaining, setTilesRemaining] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  const [selectedTile, setSelectedTile] = useState(null) // { rackIndex, letter, chosenLetter, isBlank }
  const [placedTiles, setPlacedTiles] = useState([])
  const [preview, setPreview] = useState(null)
  const [lastMoveTiles, setLastMoveTiles] = useState([]) // Array of {row, col} for last move highlighting

  const [showExchangeModal, setShowExchangeModal] = useState(false)
  const [exchangeSelection, setExchangeSelection] = useState([])

  // Blank tile selection
  const [showBlankModal, setShowBlankModal] = useState(false)
  const [pendingBlankTile, setPendingBlankTile] = useState(null) // { rackIndex, availableIdx }

  // More menu and modals
  const [showMoreMenu, setShowMoreMenu] = useState(false)
  const [showTileBagModal, setShowTileBagModal] = useState(false)
  const [showHistoryModal, setShowHistoryModal] = useState(false)
  const [tileBagContents, setTileBagContents] = useState(null)
  const [gameHistory, setGameHistory] = useState(null)
  const moreMenuRef = useRef(null)

  // Zoom state: true = zoomed in (1:1), false = zoomed out (fit to screen)
  const [isZoomedIn, setIsZoomedIn] = useState(false)
  const lastTapRef = useRef(0)

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

      // Parse last move tiles for highlighting
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

  // Poll for updates when waiting for opponent
  useEffect(() => {
    if (!game || game.status !== 'active' || isYourTurn) return

    const interval = setInterval(() => {
      loadGame()
    }, 8000)

    return () => clearInterval(interval)
  }, [game?.status, isYourTurn, loadGame])

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


  // Click outside handler for More menu
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

  // Preview move when tiles are placed
  useEffect(() => {
    if (placedTiles.length === 0) {
      setPreview(null)
      return
    }

    const previewMove = async () => {
      try {
        const tiles = placedTiles.map(t => ({
          letter: t.displayLetter, // Use the chosen letter for blanks
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

  // Draw board
  useEffect(() => {
    if (!game || !canvasRef.current) return

    const canvas = canvasRef.current
    const ctx = canvas.getContext('2d')
    const board = game.board || []

    // Board background
    ctx.fillStyle = COLORS.boardBg
    ctx.fillRect(0, 0, canvas.width, canvas.height)

    // Helper to draw square with top-left and bottom-right corners rounded
    const drawSquare = (sx, sy, size, radius) => {
      ctx.beginPath()
      ctx.roundRect(sx, sy, size, size, [radius, 0, radius, 0])
    }

    // Draw empty board squares
    for (let row = 0; row < BOARD_SIZE; row++) {
      for (let col = 0; col < BOARD_SIZE; col++) {
        const x = BOARD_PADDING + col * TILE_SIZE
        const y = BOARD_PADDING + row * TILE_SIZE
        const bonus = BONUS_SQUARES[row][col]

        // Draw square with diagonal rounded corners
        ctx.fillStyle = BONUS_COLORS[bonus]
        drawSquare(x + 1, y + 1, TILE_SIZE - 2, 8)
        ctx.fill()

        // Draw thin dark border on all squares
        ctx.strokeStyle = 'rgba(0, 0, 0, 0.08)'
        ctx.lineWidth = 0.5
        drawSquare(x + 1, y + 1, TILE_SIZE - 2, 8)
        ctx.stroke()

        // Draw bonus label (only if no tile)
        const hasExistingTile = board[row]?.[col]?.letter
        const hasNewTile = placedTiles.some(t => t.row === row && t.col === col)
        if (bonus > 0 && !hasExistingTile && !hasNewTile) {
          ctx.fillStyle = bonus === 4 || bonus === 2 ? '#fff' : COLORS.text
          ctx.font = 'bold 11px -apple-system, BlinkMacSystemFont, sans-serif'
          ctx.textAlign = 'center'
          ctx.textBaseline = 'middle'
          ctx.fillText(BONUS_LABELS[bonus], x + TILE_SIZE / 2, y + TILE_SIZE / 2)
        }
      }
    }

    // Collect all tile data with positions
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

    // Create a set of tile positions for quick lookup
    const tileSet = new Set(allTiles.map(t => `${t.row},${t.col}`))

    // Find connected components using BFS
    const visited = new Set()
    const components = []
    for (const tile of allTiles) {
      const key = `${tile.row},${tile.col}`
      if (visited.has(key)) continue

      const component = []
      const queue = [tile]
      while (queue.length > 0) {
        const current = queue.shift()
        const currentKey = `${current.row},${current.col}`
        if (visited.has(currentKey)) continue
        visited.add(currentKey)
        component.push(current)

        // Check neighbors
        const neighbors = [
          { row: current.row - 1, col: current.col },
          { row: current.row + 1, col: current.col },
          { row: current.row, col: current.col - 1 },
          { row: current.row, col: current.col + 1 }
        ]
        for (const n of neighbors) {
          const nKey = `${n.row},${n.col}`
          if (tileSet.has(nKey) && !visited.has(nKey)) {
            const nTile = allTiles.find(t => t.row === n.row && t.col === n.col)
            if (nTile) queue.push(nTile)
          }
        }
      }
      components.push(component)
    }

    // Draw each connected component as a merged shape
    const inset = 1
    const radius = 6

    for (const component of components) {
      const componentSet = new Set(component.map(t => `${t.row},${t.col}`))

      // Draw shadow for the entire merged shape first
      ctx.fillStyle = COLORS.tileShadow
      for (const tile of component) {
        const x = BOARD_PADDING + tile.col * TILE_SIZE
        const y = BOARD_PADDING + tile.row * TILE_SIZE
        // Extend fill to edges where there are neighbors (no gap)
        const hasTop = componentSet.has(`${tile.row - 1},${tile.col}`)
        const hasBottom = componentSet.has(`${tile.row + 1},${tile.col}`)
        const hasLeft = componentSet.has(`${tile.row},${tile.col - 1}`)
        const hasRight = componentSet.has(`${tile.row},${tile.col + 1}`)
        const top = hasTop ? y : y + inset + 3
        const left = hasLeft ? x : x + inset + 3
        const bottom = hasBottom ? y + TILE_SIZE : y + TILE_SIZE - inset
        const right = hasRight ? x + TILE_SIZE : x + TILE_SIZE - inset
        ctx.fillRect(left, top, right - left, bottom - top)
      }

      // Draw each tile's fill with its individual color
      for (const tile of component) {
        const x = BOARD_PADDING + tile.col * TILE_SIZE
        const y = BOARD_PADDING + tile.row * TILE_SIZE

        // Each tile gets its own color
        if (tile.isNew) {
          ctx.fillStyle = COLORS.newTile
        } else if (tile.isLastMove) {
          ctx.fillStyle = COLORS.lastMoveTile
        } else {
          ctx.fillStyle = COLORS.tile
        }

        // Extend fill to edges where there are neighbors (no gap)
        const hasTop = componentSet.has(`${tile.row - 1},${tile.col}`)
        const hasBottom = componentSet.has(`${tile.row + 1},${tile.col}`)
        const hasLeft = componentSet.has(`${tile.row},${tile.col - 1}`)
        const hasRight = componentSet.has(`${tile.row},${tile.col + 1}`)

        const top = hasTop ? y : y + inset
        const left = hasLeft ? x : x + inset
        const bottom = hasBottom ? y + TILE_SIZE : y + TILE_SIZE - inset
        const right = hasRight ? x + TILE_SIZE : x + TILE_SIZE - inset

        ctx.fillRect(left, top, right - left, bottom - top)
      }


      // Draw letters and values on top
      for (const tile of component) {
        const x = BOARD_PADDING + tile.col * TILE_SIZE
        const y = BOARD_PADDING + tile.row * TILE_SIZE

        // Letter
        ctx.fillStyle = COLORS.text
        ctx.font = 'bold 24px Georgia, "Times New Roman", serif'
        ctx.textAlign = 'center'
        ctx.textBaseline = 'middle'
        ctx.fillText(tile.letter === ' ' ? '' : tile.letter, x + TILE_SIZE / 2, y + TILE_SIZE / 2 - 2)

        // Underline for blank tiles
        if (tile.isBlank && tile.letter !== ' ') {
          ctx.strokeStyle = COLORS.text
          ctx.lineWidth = 1.5
          ctx.beginPath()
          ctx.moveTo(x + 12, y + TILE_SIZE - 10)
          ctx.lineTo(x + TILE_SIZE - 12, y + TILE_SIZE - 10)
          ctx.stroke()
        }

        // Point value
        if (tile.value > 0) {
          ctx.fillStyle = COLORS.textMuted
          ctx.font = 'bold 9px -apple-system, BlinkMacSystemFont, sans-serif'
          ctx.textAlign = 'right'
          ctx.fillText(String(tile.value), x + TILE_SIZE - 6, y + TILE_SIZE - 6)
        }
      }
    }

    // Draw score indicator for valid moves
    if (preview?.valid && placedTiles.length > 0) {
      const lastTile = placedTiles[placedTiles.length - 1]
      const badgeX = BOARD_PADDING + lastTile.col * TILE_SIZE + TILE_SIZE - 4
      const badgeY = BOARD_PADDING + lastTile.row * TILE_SIZE + 4

      const scoreText = `+${preview.score}`
      ctx.font = 'bold 14px -apple-system, BlinkMacSystemFont, sans-serif'
      const textWidth = ctx.measureText(scoreText).width
      const padding = 4
      const badgeWidth = textWidth + padding * 2
      const badgeHeight = 18

      // Draw badge background
      ctx.fillStyle = '#2e7d32'
      ctx.beginPath()
      ctx.roundRect(badgeX - badgeWidth, badgeY, badgeWidth, badgeHeight, 4)
      ctx.fill()

      // Draw badge text
      ctx.fillStyle = '#fff'
      ctx.textAlign = 'right'
      ctx.textBaseline = 'top'
      ctx.fillText(scoreText, badgeX - padding, badgeY + 2)
    }
  }, [game, placedTiles, lastMoveTiles, preview])

  const getTileValue = (letter) => {
    const values = { A: 1, B: 3, C: 3, D: 2, E: 1, F: 4, G: 2, H: 4, I: 1, J: 8, K: 5, L: 1, M: 3, N: 1, O: 1, P: 3, Q: 10, R: 1, S: 1, T: 1, U: 1, V: 4, W: 4, X: 8, Y: 4, Z: 10, ' ': 0 }
    return values[letter] || 0
  }

  const handleCanvasClick = (e) => {
    if (!isYourTurn || game?.status !== 'active') return

    const canvas = canvasRef.current
    const rect = canvas.getBoundingClientRect()
    const scaleX = canvas.width / rect.width
    const scaleY = canvas.height / rect.height

    const x = (e.clientX - rect.left) * scaleX
    const y = (e.clientY - rect.top) * scaleY

    const col = Math.floor((x - BOARD_PADDING) / TILE_SIZE)
    const row = Math.floor((y - BOARD_PADDING) / TILE_SIZE)

    if (row < 0 || row >= BOARD_SIZE || col < 0 || col >= BOARD_SIZE) return

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

  // Double tap to toggle zoom
  const handleDoubleTap = (e) => {
    const now = Date.now()
    if (now - lastTapRef.current < 300) {
      e.preventDefault()
      setIsZoomedIn(prev => !prev)
      lastTapRef.current = 0
    } else {
      lastTapRef.current = now
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

    // If clicking already selected tile, deselect
    if (selectedTile && selectedTile.rackIndex === tile.originalIndex) {
      setSelectedTile(null)
      return
    }

    // If it's a blank tile, show letter picker
    if (tile.letter === ' ') {
      setPendingBlankTile({ rackIndex: tile.originalIndex, availableIdx })
      setShowBlankModal(true)
      return
    }

    // Select regular tile
    setSelectedTile({
      rackIndex: tile.originalIndex,
      letter: tile.letter,
      chosenLetter: null,
      isBlank: false,
    })
  }

  const handleBlankLetterSelect = (letter) => {
    setShowBlankModal(false)

    // Set selected tile with the chosen letter
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
      // Refresh to get the updated game state with correct last move highlighting
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
    // Fisher-Yates shuffle for available tiles only
    const usedIndices = new Set(placedTiles.map(t => t.rackIndex))
    const newRack = [...rack]

    // Get indices of available tiles
    const availableIndices = newRack
      .map((_, idx) => idx)
      .filter(idx => !usedIndices.has(idx))

    // Shuffle available tiles among themselves
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
      <div className="page">
        <Header />
        <main className="container main-content">
          <p>Loading...</p>
        </main>
      </div>
    )
  }

  if (!game) {
    return (
      <div className="page">
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
  const canvasSize = BOARD_SIZE * TILE_SIZE + BOARD_PADDING * 2

  const getEndStatusText = () => {
    if (game.status !== 'active') {
      return game.winner_id === user?.id ? 'You Won!' : game.winner_id ? 'You Lost' : 'Draw'
    }
    return null
  }

  return (
    <div className="page scrabble-page">
      <Header />
      <main className="scrabble-container">
        {/* Combined header */}
        <div className="scrabble-header">
          {/* <button className="btn btn-secondary btn-small header-back" onClick={() => navigate('/scrabble')}>
            Back
          </button> */}
          <div className="scrabble-header-scores">
            <span className={`header-score you ${game.status === 'active' && isYourTurn ? 'current-turn' : ''}`}>
              {truncateName('You')}: {scores.you}
            </span>
            {/* <span className="header-divider">|</span> */}
            <span className="header-bag">{tilesRemaining}</span>

            {/* <span className="header-divider">|</span> */}
            <span className={`header-score them ${game.status === 'active' && !isYourTurn ? 'current-turn' : ''}`}>
              {truncateName(opponent?.username)}: {scores.them}
            </span>
          </div>
          <div className="scrabble-header-status">
            {getEndStatusText() && (
              <span className="header-status ended">{getEndStatusText()}</span>
            )}
            {/* message && <span className="header-message">{message}</span> */}
            {/* error && <span className="header-error">{error}</span> */}
          </div>
        </div>

        {/* Board */}
        <div className="scrabble-board-container">
          <div
            className={`scrabble-board-wrapper ${isZoomedIn ? 'zoomed-in' : 'zoomed-out'}`}
            onTouchStart={handleDoubleTap}
            onDoubleClick={() => setIsZoomedIn(prev => !prev)}
          >
            <canvas
              ref={canvasRef}
              width={canvasSize}
              height={canvasSize}
              onClick={handleCanvasClick}
              className="scrabble-board"
            />
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

        {/* Actions */}
        {game.status === 'active' && isYourTurn && (
          <div className="scrabble-actions">
            <div className="actions-left">
              <div className="dropup-container" ref={moreMenuRef}>
                <button
                  className="btn btn-secondary"
                  onClick={() => setShowMoreMenu(!showMoreMenu)}
                >
                  More
                </button>
                {showMoreMenu && (
                  <div className="dropup-menu">
                    <button onClick={handlePass}>Pass</button>
                    <button onClick={handleResign} className="danger">Resign</button>
                    <button onClick={handleOpenTileBag}>Tile Bag</button>
                    <button onClick={handleOpenHistory}>History</button>
                  </div>
                )}
              </div>
              <button
                className="btn btn-secondary"
                onClick={() => {
                  setShowExchangeModal(true)
                  setExchangeSelection([])
                }}
              >
                Swap
              </button>
              <button className="btn btn-secondary" onClick={handleShuffle}>
                Shuffle
              </button>
            </div>
            <button
              className="btn btn-primary btn-play"
              onClick={handleSubmit}
              disabled={!preview?.valid}
            >
              Play
            </button>
          </div>
        )}

        {/* Actions when not your turn or game ended */}
        {(game.status !== 'active' || !isYourTurn) && (
          <div className="scrabble-actions">
            <div className="actions-left">
              <div className="dropup-container" ref={moreMenuRef}>
                <button
                  className="btn btn-secondary"
                  onClick={() => setShowMoreMenu(!showMoreMenu)}
                >
                  More
                </button>
                {showMoreMenu && (
                  <div className="dropup-menu">
                    <button onClick={handleOpenTileBag}>Tile Bag</button>
                    <button onClick={handleOpenHistory}>History</button>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

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
