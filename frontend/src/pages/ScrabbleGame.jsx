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

// NYT-inspired color palette - softer, easier on eyes
const COLORS = {
  boardBg: '#f0ebe3',      // warm cream background
  gridLine: '#e0dbd3',     // very subtle grid lines
  normal: '#f0ebe3',       // empty squares - same as background for uniformity
  doubleLetter: '#d4e4ed', // very soft blue
  tripleLetter: '#7faec4', // medium blue
  doubleWord: '#f2d4d4',   // very soft pink
  tripleWord: '#d4735c',   // muted terracotta
  center: '#f2d4d4',       // same as double word
  tile: '#fffef8',         // placed tiles - warm white
  tileShadow: '#d4cfc4',   // tile shadow
  tileBorder: '#c4bfb4',   // tile border
  newTile: '#ffe082',      // newly placed tiles - softer yellow
  newTileBorder: '#e6c157',
  lastMoveTile: '#c8e6c9', // last move tiles - soft green
  lastMoveBorder: '#81c784',
  text: '#2c2c2c',         // softer black
  textMuted: '#787878',    // muted text
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
  const wrapperRef = useRef(null)

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

  // Pan state
  const [isPanning, setIsPanning] = useState(false)
  const panStartRef = useRef({ x: 0, y: 0, scrollX: 0, scrollY: 0 })

  // Zoom state
  const [zoomScale, setZoomScale] = useState(1)
  const lastTapRef = useRef(0)
  const pinchStartRef = useRef(null)
  const MAX_ZOOM = 1.3

  // Calculate min zoom to fit board in container
  const getMinZoom = useCallback(() => {
    if (!wrapperRef.current) return 0.5
    const wrapper = wrapperRef.current
    const canvasSize = BOARD_SIZE * TILE_SIZE + BOARD_PADDING * 2
    const minScale = Math.min(wrapper.clientWidth / canvasSize, wrapper.clientHeight / canvasSize)
    return Math.max(0.3, minScale - 0.02) // slight margin
  }, [])

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

  // Center the board when at min zoom or on initial load
  const centerBoard = useCallback((targetScale) => {
    if (!wrapperRef.current) return
    const wrapper = wrapperRef.current
    const canvasSize = BOARD_SIZE * TILE_SIZE + BOARD_PADDING * 2
    const scale = targetScale ?? zoomScale
    const scaledSize = canvasSize * scale
    const scrollX = (scaledSize - wrapper.clientWidth) / 2
    const scrollY = (scaledSize - wrapper.clientHeight) / 2
    wrapper.scrollLeft = Math.max(0, scrollX)
    wrapper.scrollTop = Math.max(0, scrollY)
  }, [zoomScale])

  // Center the board when zoom changes or on initial load
  useEffect(() => {
    if (wrapperRef.current && game) {
      // Use requestAnimationFrame to ensure DOM has updated
      requestAnimationFrame(() => {
        centerBoard(zoomScale)
      })
    }
  }, [game, zoomScale])

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

    for (let row = 0; row < BOARD_SIZE; row++) {
      for (let col = 0; col < BOARD_SIZE; col++) {
        const x = BOARD_PADDING + col * TILE_SIZE
        const y = BOARD_PADDING + row * TILE_SIZE
        const bonus = BONUS_SQUARES[row][col]

        // Draw square
        ctx.fillStyle = BONUS_COLORS[bonus]
        ctx.fillRect(x + 1, y + 1, TILE_SIZE - 2, TILE_SIZE - 2)

        // Draw thin dark border on all squares
        ctx.strokeStyle = 'rgba(0, 0, 0, 0.12)'
        ctx.lineWidth = 0.5
        ctx.strokeRect(x + 1, y + 1, TILE_SIZE - 2, TILE_SIZE - 2)

        // Draw bonus label
        if (bonus > 0 && (!board[row] || !board[row][col]?.letter)) {
          ctx.fillStyle = bonus === 4 || bonus === 2 ? '#fff' : COLORS.text
          ctx.font = 'bold 11px -apple-system, BlinkMacSystemFont, sans-serif'
          ctx.textAlign = 'center'
          ctx.textBaseline = 'middle'
          ctx.fillText(BONUS_LABELS[bonus], x + TILE_SIZE / 2, y + TILE_SIZE / 2)
        }

        // Draw placed tiles
        if (board[row] && board[row][col]?.letter) {
          const isBlank = board[row][col].value === 0 && board[row][col].letter !== ' '
          const isLastMove = lastMoveTiles.some(t => t.row === row && t.col === col)
          drawTile(ctx, x, y, board[row][col].letter, board[row][col].value, false, isBlank, isLastMove)
        }
      }
    }

    // Draw newly placed tiles
    for (const tile of placedTiles) {
      const x = BOARD_PADDING + tile.col * TILE_SIZE
      const y = BOARD_PADDING + tile.row * TILE_SIZE
      drawTile(ctx, x, y, tile.displayLetter, tile.isBlank ? 0 : getTileValue(tile.displayLetter), true, tile.isBlank, false)
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

  const drawTile = (ctx, x, y, letter, value, isNew = false, isBlank = false, isLastMove = false) => {
    let tileColor = COLORS.tile
    let borderColor = COLORS.tileBorder

    if (isNew) {
      tileColor = COLORS.newTile
      borderColor = COLORS.newTileBorder
    } else if (isLastMove) {
      tileColor = COLORS.lastMoveTile
      borderColor = COLORS.lastMoveBorder
    }

    // Subtle shadow
    ctx.fillStyle = COLORS.tileShadow
    ctx.fillRect(x + 4, y + 4, TILE_SIZE - 6, TILE_SIZE - 6)

    // Tile face
    ctx.fillStyle = tileColor
    ctx.fillRect(x + 2, y + 2, TILE_SIZE - 6, TILE_SIZE - 6)

    // Border
    ctx.strokeStyle = borderColor
    ctx.lineWidth = 1
    ctx.strokeRect(x + 2, y + 2, TILE_SIZE - 6, TILE_SIZE - 6)

    // Letter
    ctx.fillStyle = COLORS.text
    ctx.font = 'bold 24px Georgia, "Times New Roman", serif'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText(letter === ' ' ? '' : letter, x + TILE_SIZE / 2, y + TILE_SIZE / 2 - 2)

    // Underline for blank tiles
    if (isBlank && letter !== ' ') {
      ctx.strokeStyle = COLORS.text
      ctx.lineWidth = 1.5
      ctx.beginPath()
      ctx.moveTo(x + 12, y + TILE_SIZE - 10)
      ctx.lineTo(x + TILE_SIZE - 12, y + TILE_SIZE - 10)
      ctx.stroke()
    }

    // Point value
    if (value > 0) {
      ctx.fillStyle = COLORS.textMuted
      ctx.font = 'bold 9px -apple-system, BlinkMacSystemFont, sans-serif'
      ctx.textAlign = 'right'
      ctx.fillText(String(value), x + TILE_SIZE - 6, y + TILE_SIZE - 6)
    }
  }

  const getTileValue = (letter) => {
    const values = { A: 1, B: 3, C: 3, D: 2, E: 1, F: 4, G: 2, H: 4, I: 1, J: 8, K: 5, L: 1, M: 3, N: 1, O: 1, P: 3, Q: 10, R: 1, S: 1, T: 1, U: 1, V: 4, W: 4, X: 8, Y: 4, Z: 10, ' ': 0 }
    return values[letter] || 0
  }

  const handleCanvasClick = (e) => {
    if (!isYourTurn || game?.status !== 'active') return
    if (isPanning) return

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

  // Pan handlers with touch support
  const handlePanStart = (e) => {
    // Handle pinch start
    if (e.touches && e.touches.length === 2) {
      e.preventDefault()
      const touch1 = e.touches[0]
      const touch2 = e.touches[1]
      const dist = Math.hypot(touch1.clientX - touch2.clientX, touch1.clientY - touch2.clientY)
      const midX = (touch1.clientX + touch2.clientX) / 2
      const midY = (touch1.clientY + touch2.clientY) / 2
      pinchStartRef.current = {
        dist,
        scale: zoomScale,
        midX,
        midY,
        scrollX: wrapperRef.current?.scrollLeft || 0,
        scrollY: wrapperRef.current?.scrollTop || 0,
      }
      return
    }

    if (e.touches && e.touches.length > 1) return

    const clientX = e.touches ? e.touches[0].clientX : e.clientX
    const clientY = e.touches ? e.touches[0].clientY : e.clientY

    panStartRef.current = {
      x: clientX,
      y: clientY,
      scrollX: wrapperRef.current?.scrollLeft || 0,
      scrollY: wrapperRef.current?.scrollTop || 0,
    }
    setIsPanning(false)
  }

  const handlePanMove = (e) => {
    // Handle pinch zoom
    if (e.touches && e.touches.length === 2 && pinchStartRef.current) {
      e.preventDefault()
      const touch1 = e.touches[0]
      const touch2 = e.touches[1]
      const dist = Math.hypot(touch1.clientX - touch2.clientX, touch1.clientY - touch2.clientY)
      const scaleFactor = dist / pinchStartRef.current.dist
      const minZoom = getMinZoom()
      const newScale = Math.min(MAX_ZOOM, Math.max(minZoom, pinchStartRef.current.scale * scaleFactor))

      // Calculate new scroll position to keep pinch center stationary
      const wrapper = wrapperRef.current
      if (wrapper) {
        const rect = wrapper.getBoundingClientRect()
        const pinchCenterX = pinchStartRef.current.midX - rect.left
        const pinchCenterY = pinchStartRef.current.midY - rect.top

        // Point in content at pinch start
        const contentX = (pinchStartRef.current.scrollX + pinchCenterX) / pinchStartRef.current.scale
        const contentY = (pinchStartRef.current.scrollY + pinchCenterY) / pinchStartRef.current.scale

        // New scroll to keep that point under pinch center
        const newScrollX = contentX * newScale - pinchCenterX
        const newScrollY = contentY * newScale - pinchCenterY

        wrapper.scrollLeft = Math.max(0, newScrollX)
        wrapper.scrollTop = Math.max(0, newScrollY)
      }

      setZoomScale(newScale)
      return
    }

    const panStart = panStartRef.current
    if (!panStart.x && !panStart.y) return

    // Prevent page scroll on touch devices
    if (e.touches) {
      e.preventDefault()
    }

    const clientX = e.touches ? e.touches[0].clientX : e.clientX
    const clientY = e.touches ? e.touches[0].clientY : e.clientY

    const deltaX = panStart.x - clientX
    const deltaY = panStart.y - clientY

    if (Math.abs(deltaX) > 5 || Math.abs(deltaY) > 5) {
      setIsPanning(true)
    }

    if (wrapperRef.current) {
      wrapperRef.current.scrollLeft = panStart.scrollX + deltaX
      wrapperRef.current.scrollTop = panStart.scrollY + deltaY
    }
  }

  const handlePanEnd = () => {
    panStartRef.current = { x: 0, y: 0, scrollX: 0, scrollY: 0 }
    pinchStartRef.current = null
    setTimeout(() => setIsPanning(false), 50)
  }

  const handleDoubleTap = (e) => {
    const now = Date.now()
    const DOUBLE_TAP_DELAY = 300

    if (now - lastTapRef.current < DOUBLE_TAP_DELAY) {
      e.preventDefault()

      const wrapper = wrapperRef.current
      if (!wrapper) return

      const rect = wrapper.getBoundingClientRect()

      // Tap position relative to wrapper (fallback to center)
      const tapX = e.touches
        ? e.touches[0].clientX - rect.left
        : rect.width / 2
      const tapY = e.touches
        ? e.touches[0].clientY - rect.top
        : rect.height / 2

      const minZoom = getMinZoom()
      const targetScale =
        zoomScale < minZoom + 0.1 ? 1 : minZoom

      // Convert tap point to content coordinates
      const contentX = (wrapper.scrollLeft + tapX) / zoomScale
      const contentY = (wrapper.scrollTop + tapY) / zoomScale

      // Apply zoom
      setZoomScale(targetScale)

      // Re-center scroll so tap stays fixed
      requestAnimationFrame(() => {
        wrapper.scrollLeft = contentX * targetScale - tapX
        wrapper.scrollTop = contentY * targetScale - tapY
      })

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
            ref={wrapperRef}
            className="scrabble-board-wrapper"
            onMouseDown={handlePanStart}
            onMouseMove={handlePanMove}
            onMouseUp={handlePanEnd}
            onMouseLeave={handlePanEnd}
            onTouchStart={(e) => { handlePanStart(e); handleDoubleTap(e); }}
            onTouchMove={handlePanMove}
            onTouchEnd={handlePanEnd}
            onDoubleClick={() => {
              const minZoom = getMinZoom()
              if (zoomScale < minZoom + 0.1) {
                setZoomScale(1)
              } else {
                setZoomScale(minZoom)
              }
            }}
          >
            <div
              className="scrabble-board-scaler"
              style={{
                width: canvasSize * zoomScale,
                height: canvasSize * zoomScale,
              }}
            >
              <canvas
                ref={canvasRef}
                width={canvasSize}
                height={canvasSize}
                onClick={handleCanvasClick}
                className="scrabble-board"
                style={{ transform: `scale(${zoomScale})`, transformOrigin: 'top left' }}
              />
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
