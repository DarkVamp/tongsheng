// Mono Icons SVG — https://github.com/mono-company/mono-icons (MIT)
const icons = {
  'chevron-down': 'M5.293 8.293a1 1 0 0 1 1.414 0L12 13.586l5.293-5.293a1 1 0 1 1 1.414 1.414l-6 6a1 1 0 0 1-1.414 0l-6-6a1 1 0 0 1 0-1.414z',
}

export default function Icon({ name, size = 20, className = '', style = {} }) {
  const path = icons[name]
  if (!path) return null
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      style={style}
      aria-hidden="true"
    >
      <path d={path} fill="currentColor" fillRule="evenodd" clipRule="evenodd" />
    </svg>
  )
}
