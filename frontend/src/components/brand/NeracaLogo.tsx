interface NeracaLogoProps {
  size?: number;
  className?: string;
}

export function NeracaLogo({ size = 48, className }: NeracaLogoProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 48 48"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      role="img"
      aria-label="Logo Neraca"
    >
      <rect width="48" height="48" rx="14" fill="#1c1c1e" />

      {/* tiang, alas, dan batang timbangan */}
      <g stroke="#ffffff" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M24 12V35" />
        <path d="M16 36H32" />
        <path d="M11 17H37" />
        {/* tali piringan kiri */}
        <path d="M11 17L5 27M11 17L17 27" strokeWidth="1.5" />
        {/* tali piringan kanan */}
        <path d="M37 17L31 27M37 17L43 27" strokeWidth="1.5" />
      </g>

      {/* poros */}
      <circle cx="24" cy="12" r="2.5" fill="#ffffff" />

      {/* piringan */}
      <path d="M5 27H17A6 6 0 0 1 5 27Z" fill="#ffffff" />
      <path d="M31 27H43A6 6 0 0 1 31 27Z" fill="#b9aef0" />
    </svg>
  );
}