import { useState, useEffect } from 'react'
import Header from '../components/Header'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/api'

export default function Friends() {
  const { user } = useAuth()
  const [friends, setFriends] = useState([])
  const [requests, setRequests] = useState([])
  const [friendCode, setFriendCode] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      const [friendsData, requestsData] = await Promise.all([
        api.getFriends(),
        api.getPendingRequests(),
      ])
      setFriends(friendsData)
      setRequests(requestsData)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const handleSendRequest = async (e) => {
    e.preventDefault()
    setError('')
    setSuccess('')

    const code = friendCode.trim()
    if (!code) {
      setError('Please enter a friend code')
      return
    }

    try {
      await api.sendFriendRequest(code)
      setSuccess('Friend request sent!')
      setFriendCode('')
    } catch (err) {
      setError(err.message)
    }
  }

  const handleAccept = async (requestId) => {
    setError('')
    try {
      await api.respondToFriendRequest(requestId, 'accept')
      await loadData()
    } catch (err) {
      setError(err.message)
    }
  }

  const handleDeny = async (requestId) => {
    setError('')
    try {
      await api.respondToFriendRequest(requestId, 'deny')
      setRequests(requests.filter((r) => r.id !== requestId))
    } catch (err) {
      setError(err.message)
    }
  }

  const handleRemove = async (friendId) => {
    setError('')
    try {
      await api.removeFriend(friendId)
      setFriends(friends.filter((f) => f.friend_id !== friendId))
    } catch (err) {
      setError(err.message)
    }
  }

  const copyFriendCode = () => {
    navigator.clipboard.writeText(user?.friend_code || '')
    setSuccess('Friend code copied!')
    setTimeout(() => setSuccess(''), 2000)
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

  return (
    <div className="page">
      <Header />
      <main className="container main-content">
        <h1 className="page-title">Friends</h1>

        {error && <div className="alert alert-error">{error}</div>}
        {success && <div className="alert alert-success">{success}</div>}

        {/* Your Friend Code */}
        <div className="card mb-2">
          <h2 className="section-title">Your Friend Code</h2>
          <div className="friend-code-display">
            <code className="friend-code">{user?.friend_code}</code>
            <button onClick={copyFriendCode} className="btn btn-secondary">
              Copy
            </button>
          </div>
          <p className="text-muted text-small mt-1">
            Share this code with friends so they can add you
          </p>
        </div>

        {/* Add Friend */}
        <div className="card mb-2">
          <h2 className="section-title">Add Friend</h2>
          <form onSubmit={handleSendRequest} className="add-friend-form">
            <input
              type="text"
              value={friendCode}
              onChange={(e) => setFriendCode(e.target.value)}
              placeholder="Enter friend code"
              className="friend-code-input"
            />
            <button type="submit" className="btn btn-primary">
              Send Request
            </button>
          </form>
        </div>

        {/* Pending Requests */}
        {requests.length > 0 && (
          <div className="card mb-2">
            <h2 className="section-title">Friend Requests</h2>
            <ul className="friend-list">
              {requests.map((request) => (
                <li key={request.id} className="friend-item">
                  <span className="friend-name">{request.sender?.username}</span>
                  <div className="friend-actions">
                    <button
                      onClick={() => handleAccept(request.id)}
                      className="btn btn-primary btn-small"
                    >
                      Accept
                    </button>
                    <button
                      onClick={() => handleDeny(request.id)}
                      className="btn btn-secondary btn-small"
                    >
                      Deny
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Friends List */}
        <div className="card">
          <h2 className="section-title">My Friends</h2>
          {friends.length === 0 ? (
            <p className="text-muted">No friends yet. Add some friends using their friend code!</p>
          ) : (
            <ul className="friend-list">
              {friends.map((friendship) => (
                <li key={friendship.id} className="friend-item">
                  <span className="friend-name">{friendship.friend?.username}</span>
                  <button
                    onClick={() => handleRemove(friendship.friend_id)}
                    className="btn btn-danger btn-small"
                  >
                    Remove
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </main>
    </div>
  )
}
