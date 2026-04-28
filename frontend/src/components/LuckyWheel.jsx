import { useRef, useState, useEffect } from 'react'
import { X } from 'lucide-react'

const COLORS = [
  '#4f46e5', '#e11d48', '#d97706', '#059669', '#7c3aed',
  '#db2777', '#2563eb', '#16a34a', '#ea580c', '#0891b2',
  '#9333ea', '#c2410c', '#0d9488', '#b45309', '#1d4ed8',
]

function getWinnerIndex(finalRotation, n) {
  const sliceAngle = (2 * Math.PI) / n
  // pointer is at top = canvas angle 3π/2 (270°)
  const effectiveAngle = ((3 * Math.PI / 2 - finalRotation) % (2 * Math.PI) + 2 * Math.PI) % (2 * Math.PI)
  return Math.floor(effectiveAngle / sliceAngle) % n
}

export default function LuckyWheel({ students, onClose, t }) {
  const canvasRef = useRef(null)
  const [spinning, setSpinning] = useState(false)
  const [winner, setWinner] = useState(null)
  const rotationRef = useRef(0)
  const rafRef = useRef(null)

  const n = students.length
  const sliceAngle = n > 0 ? (2 * Math.PI) / n : 0

  const drawWheel = (rotation) => {
    const canvas = canvasRef.current
    if (!canvas || n === 0) return
    const ctx = canvas.getContext('2d')
    const size = canvas.width
    const cx = size / 2
    const cy = size / 2
    const r = size / 2 - 14

    ctx.clearRect(0, 0, size, size)

    for (let i = 0; i < n; i++) {
      const startAngle = rotation + i * sliceAngle
      const endAngle = startAngle + sliceAngle

      ctx.beginPath()
      ctx.moveTo(cx, cy)
      ctx.arc(cx, cy, r, startAngle, endAngle)
      ctx.closePath()
      ctx.fillStyle = COLORS[i % COLORS.length]
      ctx.fill()
      ctx.strokeStyle = '#fff'
      ctx.lineWidth = 2.5
      ctx.stroke()

      ctx.save()
      ctx.translate(cx, cy)
      ctx.rotate(startAngle + sliceAngle / 2)
      ctx.textAlign = 'right'
      ctx.fillStyle = '#fff'
      const fontSize = Math.max(11, Math.min(22, 200 / n))
      ctx.font = `bold ${fontSize}px system-ui, sans-serif`
      ctx.shadowColor = 'rgba(0,0,0,0.5)'
      ctx.shadowBlur = 3
      ctx.fillText(students[i].name, r - 12, fontSize * 0.38)
      ctx.restore()
    }

    // center hub
    ctx.beginPath()
    ctx.arc(cx, cy, 20, 0, 2 * Math.PI)
    ctx.fillStyle = '#fff'
    ctx.fill()
    ctx.strokeStyle = '#d1d5db'
    ctx.lineWidth = 2
    ctx.stroke()

    ctx.beginPath()
    ctx.arc(cx, cy, 7, 0, 2 * Math.PI)
    ctx.fillStyle = '#6b7280'
    ctx.fill()
  }

  useEffect(() => {
    rotationRef.current = 0
    setWinner(null)
    drawWheel(0)
  }, [students])

  const spin = () => {
    if (spinning || n === 0) return
    setSpinning(true)
    setWinner(null)

    const extraTurns = 8 + Math.floor(Math.random() * 5)
    const extraAngle = Math.random() * 2 * Math.PI
    const totalRotation = extraTurns * 2 * Math.PI + extraAngle
    const duration = 6000 + Math.random() * 2000
    const startRotation = rotationRef.current
    const startTime = performance.now()

    const animate = (now) => {
      const elapsed = now - startTime
      const progress = Math.min(elapsed / duration, 1)
      // two-phase: fast spin for first 70%, then creeps through last 30%
      // last 30% of time covers only 10% of rotation → spannende Verlangsamung
      const eased = progress < 0.70
        ? progress * (1 / 0.70) * 0.90
        : 0.90 + 0.10 * (1 - Math.pow(1 - (progress - 0.70) / 0.30, 3))
      const current = startRotation + totalRotation * eased
      rotationRef.current = current
      drawWheel(current)

      if (progress < 1) {
        rafRef.current = requestAnimationFrame(animate)
      } else {
        const normalized = ((current % (2 * Math.PI)) + 2 * Math.PI) % (2 * Math.PI)
        setWinner(students[getWinnerIndex(normalized, n)])
        setSpinning(false)
      }
    }

    rafRef.current = requestAnimationFrame(animate)
  }

  useEffect(() => () => { if (rafRef.current) cancelAnimationFrame(rafRef.current) }, [])

  return (
    <div className="modal-overlay wheel-overlay" onClick={!spinning ? onClose : undefined}>
      <div className="wheel-modal" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h2>{t('teacher.luckyWheel')}</h2>
          <button className="btn-icon btn-ghost" onClick={!spinning ? onClose : undefined} disabled={spinning}>
            <X size={18} />
          </button>
        </div>

        {n === 0 ? (
          <p className="empty wheel-empty">{t('teacher.noPresentStudents')}</p>
        ) : (
          <>
            <div className="wheel-wrap">
              <div className="wheel-pointer" aria-hidden="true" />
              <canvas ref={canvasRef} width={460} height={460} className="wheel-canvas" />
            </div>

            <div className={`wheel-winner${winner ? ' visible' : ''}`}>
              {winner ? <>🎉 <span>{winner.name}</span></> : <>&nbsp;</>}
            </div>

            <div className="wheel-actions">
              <button className="btn-primary btn-spin" onClick={spin} disabled={spinning}>
                {spinning ? '…' : winner ? t('teacher.spinAgain') : t('teacher.spin')}
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  )
}
