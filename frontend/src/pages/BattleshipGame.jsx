import { useParams } from 'react-router-dom'
import Header from '../components/Header'

export default function BattleshipGame() {
  const { id } = useParams()

  return (
    <div className="page">
      <Header />
      <main className="container main-content">
        <h1 className="page-title">Battleship</h1>
        <p className="text-muted">Game #{id}</p>
        <p>Coming soon...</p>
      </main>
    </div>
  )
}
