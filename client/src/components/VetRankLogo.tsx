import { useTheme } from "@/contexts/ThemeContext";

interface VetRankLogoProps {
  className?: string;
  /** Omite o subtítulo e recorta o viewBox — ideal para navbar */
  compact?: boolean;
}

export default function VetRankLogo({ className = "h-10 w-auto", compact = false }: VetRankLogoProps) {
  const { theme } = useTheme();
  const isDark = theme === "dark";

  // compact: remove o subtítulo e estreita a largura (escudo vai até y=148)
  const viewBox = compact ? "0 0 420 148" : "0 0 550 150";

  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox={viewBox}
      className={className}
    >
      <defs>
        <linearGradient id="vrl-green" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#10B981" />
          <stop offset="100%" stopColor="#047857" />
        </linearGradient>
      </defs>

      {/* Ícone escudo */}
      <g transform="translate(30, 15)">
        <path d="M20,45 L80,45 L80,85 C80,108 50,125 50,125 C50,125 20,108 20,85 Z" fill="#0F172A" />
        <path d="M26,51 L74,51 L74,83 C74,101 50,114 50,114 C50,114 26,101 26,83 Z" fill="none" stroke="#10B981" strokeWidth="2" opacity="0.5" />
        <path d="M43,62 h14 v10 h10 v14 h-10 v10 h-14 v-10 h-10 v-14 h10 z" fill="#10B981" />
        <polygon points="50,10 95,27 50,44 5,27" fill="url(#vrl-green)" />
        <polygon points="50,16 85,27 50,38 15,27" fill="#064E3B" opacity="0.3" />
        <path d="M50,27 L92,40 L92,60" fill="none" stroke="#F59E0B" strokeWidth="2" />
        <circle cx="92" cy="63" r="3.5" fill="#F59E0B" />
      </g>

      {/* "Vet" — cor adapta ao tema */}
      <text
        x="140" y="88"
        fontFamily="'Georgia', 'Times New Roman', serif"
        fontSize="56"
        fontWeight="bold"
        fill={isDark ? "#FFFFFF" : "#0F172A"}
      >
        Vet
      </text>

      {/* "Rank" — sempre verde */}
      <text
        x="235" y="88"
        fontFamily="'Montserrat', 'Segoe UI', sans-serif"
        fontSize="56"
        fontWeight="800"
        fill="url(#vrl-green)"
      >
        Rank
      </text>

      {/* Subtítulo — oculto no modo compacto pelo recorte do viewBox */}
      {!compact && (
        <text
          x="145" y="118"
          fontFamily="'Montserrat', 'Segoe UI', sans-serif"
          fontSize="12"
          fontWeight="700"
          fill={isDark ? "#94A3B8" : "#64748B"}
          letterSpacing="3.5"
        >
          PORTAL DE ESTUDOS VETERINÁRIOS
        </text>
      )}
    </svg>
  );
}
