const API_BASE = '/api'

class ApiService {
  constructor() {
    this.accessToken = localStorage.getItem('accessToken')
    this.refreshToken = localStorage.getItem('refreshToken')
  }

  setTokens(accessToken, refreshToken) {
    this.accessToken = accessToken
    this.refreshToken = refreshToken
    localStorage.setItem('accessToken', accessToken)
    if (refreshToken) {
      localStorage.setItem('refreshToken', refreshToken)
    }
  }

  clearTokens() {
    this.accessToken = null
    this.refreshToken = null
    localStorage.removeItem('accessToken')
    localStorage.removeItem('refreshToken')
  }

  async request(endpoint, options = {}) {
    const url = `${API_BASE}${endpoint}`
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers,
    }

    if (this.accessToken) {
      headers['Authorization'] = `Bearer ${this.accessToken}`
    }

    const response = await fetch(url, {
      ...options,
      headers,
    })

    // Handle token refresh on 401
    if (response.status === 401 && this.refreshToken && endpoint !== '/refresh') {
      const refreshed = await this.refresh()
      if (refreshed) {
        headers['Authorization'] = `Bearer ${this.accessToken}`
        return fetch(url, { ...options, headers })
      }
    }

    return response
  }

  async register(username, password) {
    const response = await this.request('/register', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.error || 'Registration failed')
    }

    this.setTokens(data.access_token, data.refresh_token)
    return data.user
  }

  async login(username, password) {
    const response = await this.request('/login', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.error || 'Login failed')
    }

    this.setTokens(data.access_token, data.refresh_token)
    return data.user
  }

  async refresh() {
    try {
      const response = await fetch(`${API_BASE}/refresh`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: this.refreshToken }),
      })

      if (!response.ok) {
        this.clearTokens()
        return false
      }

      const data = await response.json()
      this.setTokens(data.access_token, null)
      return true
    } catch {
      this.clearTokens()
      return false
    }
  }

  async getMe() {
    const response = await this.request('/me')

    if (!response.ok) {
      throw new Error('Failed to get user')
    }

    return response.json()
  }

  logout() {
    this.clearTokens()
  }

  // Friends API
  async getFriends() {
    const response = await this.request('/friends')
    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.error || 'Failed to get friends')
    }
    return response.json()
  }

  async sendFriendRequest(friendCode) {
    const response = await this.request('/friends/request', {
      method: 'POST',
      body: JSON.stringify({ friend_code: friendCode }),
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to send friend request')
    }
    return data
  }

  async getPendingRequests() {
    const response = await this.request('/friends/requests')
    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.error || 'Failed to get friend requests')
    }
    return response.json()
  }

  async respondToFriendRequest(requestId, action) {
    const response = await this.request(`/friends/requests/${requestId}`, {
      method: 'POST',
      body: JSON.stringify({ action }),
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to respond to friend request')
    }
    return data
  }

  async removeFriend(friendId) {
    const response = await this.request(`/friends/${friendId}`, {
      method: 'DELETE',
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to remove friend')
    }
    return data
  }

  // Scrabble API
  async getScrabbleGames() {
    const response = await this.request('/scrabble/games')
    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.error || 'Failed to get games')
    }
    return response.json()
  }

  async createScrabbleGame(opponentId) {
    const response = await this.request('/scrabble/games', {
      method: 'POST',
      body: JSON.stringify({ opponent_id: opponentId }),
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to create game')
    }
    return data
  }

  async getScrabbleGame(gameId) {
    const response = await this.request(`/scrabble/games/${gameId}`)
    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.error || 'Failed to get game')
    }
    return response.json()
  }

  async playScrabbleMove(gameId, tiles) {
    const response = await this.request(`/scrabble/games/${gameId}/play`, {
      method: 'POST',
      body: JSON.stringify({ tiles }),
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to play move')
    }
    return data
  }

  async previewScrabbleMove(gameId, tiles) {
    const response = await this.request(`/scrabble/games/${gameId}/preview`, {
      method: 'POST',
      body: JSON.stringify({ tiles }),
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to preview move')
    }
    return data
  }

  async passScrabbleTurn(gameId) {
    const response = await this.request(`/scrabble/games/${gameId}/pass`, {
      method: 'POST',
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to pass turn')
    }
    return data
  }

  async exchangeScrabbleTiles(gameId, tiles) {
    const response = await this.request(`/scrabble/games/${gameId}/exchange`, {
      method: 'POST',
      body: JSON.stringify({ tiles }),
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to exchange tiles')
    }
    return data
  }

  async resignScrabbleGame(gameId) {
    const response = await this.request(`/scrabble/games/${gameId}/resign`, {
      method: 'POST',
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to resign game')
    }
    return data
  }

  async getTileBag(gameId) {
    const response = await this.request(`/scrabble/games/${gameId}/bag`)
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to get tile bag')
    }
    return data
  }

  async getGameHistory(gameId) {
    const response = await this.request(`/scrabble/games/${gameId}/history`)
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to get game history')
    }
    return data
  }

  // Battleship API
  async getBattleshipGames() {
    const response = await this.request('/battleship/games')
    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.error || 'Failed to get games')
    }
    return response.json()
  }

  async createBattleshipGame(opponentId) {
    const response = await this.request('/battleship/games', {
      method: 'POST',
      body: JSON.stringify({ opponent_id: opponentId }),
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to create game')
    }
    return data
  }

  async getBattleshipGame(gameId) {
    const response = await this.request(`/battleship/games/${gameId}`)
    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.error || 'Failed to get game')
    }
    return response.json()
  }

  async placeBattleshipShips(gameId, ships) {
    const response = await this.request(`/battleship/games/${gameId}/ships`, {
      method: 'POST',
      body: JSON.stringify({ ships }),
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to place ships')
    }
    return data
  }

  async fireBattleshipShot(gameId, row, col) {
    const response = await this.request(`/battleship/games/${gameId}/fire`, {
      method: 'POST',
      body: JSON.stringify({ row, col }),
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to fire shot')
    }
    return data
  }

  async resignBattleshipGame(gameId) {
    const response = await this.request(`/battleship/games/${gameId}/resign`, {
      method: 'POST',
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.error || 'Failed to resign game')
    }
    return data
  }
}

export const api = new ApiService()
