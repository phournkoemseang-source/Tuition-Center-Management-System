/**
 * Celebration for moments the teacher shouldn't miss (e.g. a new student was created):
 * a soft three-note chime (Web Audio, no asset files) + a light confetti burst.
 * Both degrade gracefully: reduced-motion users get no confetti, and audio
 * failures are swallowed so they never break the app flow.
 */

const COLORS = ['#6366f1', '#818cf8', '#10b981', '#34d399', '#f59e0b', '#fbbf24', '#f43f5e', '#38bdf8']

export function celebrate() {
  burstConfetti()
  playChime()
}

function burstConfetti(count = 70) {
  if (typeof document === 'undefined') return
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return

  const host = document.createElement('div')
  host.setAttribute('aria-hidden', 'true')
  Object.assign(host.style, {
    position: 'fixed',
    inset: '0',
    pointerEvents: 'none',
    zIndex: '90',
    overflow: 'hidden',
  })
  document.body.appendChild(host)

  const w = window.innerWidth
  const h = window.innerHeight
  let maxDur = 0

  for (let i = 0; i < count; i++) {
    const piece = document.createElement('div')
    const size = 6 + Math.random() * 7
    const round = Math.random() < 0.3
    const x = w * 0.5 + (Math.random() - 0.5) * w * 0.7
    const drift = (Math.random() - 0.5) * 160
    const dur = 2000 + Math.random() * 1400
    maxDur = Math.max(maxDur, dur)

    Object.assign(piece.style, {
      position: 'absolute',
      left: `${x}px`,
      top: '-16px',
      width: `${size}px`,
      height: round ? `${size}px` : `${size * (0.4 + Math.random() * 0.5)}px`,
      background: COLORS[i % COLORS.length] ?? '#6366f1',
      borderRadius: round ? '50%' : '2px',
      opacity: '0.95',
    })

    piece
      .animate(
        [
          { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
          {
            transform: `translate(${drift}px, ${h * 0.55}px) rotate(${180 + Math.random() * 360}deg)`,
            opacity: 1,
            offset: 0.55,
          },
          {
            transform: `translate(${drift * 1.6}px, ${h + 40}px) rotate(${360 + Math.random() * 540}deg)`,
            opacity: 0,
          },
        ],
        { duration: dur, easing: 'cubic-bezier(0.3, 0.4, 0.6, 1)', fill: 'forwards' },
      )
      .addEventListener('finish', () => piece.remove())

    host.appendChild(piece)
  }

  setTimeout(() => host.remove(), maxDur + 300)
}

let audioCtx: AudioContext | null = null

function playChime() {
  try {
    const Ctor =
      window.AudioContext ??
      (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
    if (!Ctor) return
    audioCtx ??= new Ctor()
    if (audioCtx.state === 'suspended') void audioCtx.resume()

    // Soft A-major arpeggio: A5 → C#6 → E6
    const notes = [880, 1108.73, 1318.51]
    const start = audioCtx.currentTime + 0.02
    notes.forEach((freq, i) => {
      const ctx = audioCtx!
      const t0 = start + i * 0.085
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.type = 'sine'
      osc.frequency.value = freq
      gain.gain.setValueAtTime(0.0001, t0)
      gain.gain.exponentialRampToValueAtTime(0.055, t0 + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.5)
      osc.connect(gain)
      gain.connect(ctx.destination)
      osc.start(t0)
      osc.stop(t0 + 0.55)
    })
  } catch {
    // Audio is a nicety — never let it break the flow
  }
}
