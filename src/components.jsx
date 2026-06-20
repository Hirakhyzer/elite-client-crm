import { useEffect } from "react";
import { initials } from "./utils";

const icons = {
  dashboard: "▦",
  clients: "◉",
  pipeline: "▤",
  calendar: "◷",
  invoices: "▧",
  emails: "✉",
  goals: "◎",
  plus: "＋",
  edit: "✎",
  trash: "⌫",
  mail: "✉",
  money: "$",
  chart: "▥",
  star: "★",
  search: "⌕",
};

export function Icon({ name, className = "" }) {
  return <span className={`icon ${className}`} aria-hidden="true">{icons[name] || name}</span>;
}

export function Avatar({ name, size = 36 }) {
  return <span className="avatar" style={{ width: size, height: size, fontSize: Math.max(11, size * 0.34) }}>{initials(name)}</span>;
}

export function StatusBadge({ status }) {
  return <span className={`badge status-${status.toLowerCase()}`}>{status}</span>;
}

export function TierBadge({ tier }) {
  return <span className={`badge tier-${tier.toLowerCase()}`}>{tier}</span>;
}

export function Chip({ children }) {
  return <span className="chip">{children}</span>;
}

export function ScoreBar({ score }) {
  const tone = score >= 80 ? "gold" : score >= 60 ? "orange" : "red";
  return <div className="score"><div><i className={tone} style={{ width: `${score}%` }} /></div><b>{score}</b></div>;
}

export function Button({ children, variant = "gold", className = "", ...props }) {
  return <button className={`btn ${variant} ${className}`} {...props}>{children}</button>;
}

export function Card({ children, className = "" }) {
  return <div className={`card ${className}`}>{children}</div>;
}

export function SectionTitle({ title, action }) {
  return <div className="section-title"><h2>{title}</h2>{action}</div>;
}

export function Modal({ title, children, onClose, wide = false }) {
  useEffect(() => {
    const close = (event) => event.key === "Escape" && onClose();
    window.addEventListener("keydown", close);
    return () => window.removeEventListener("keydown", close);
  }, [onClose]);

  return <div className="modal-backdrop" onMouseDown={onClose}>
    <div className={`modal ${wide ? "wide" : ""}`} onMouseDown={(event) => event.stopPropagation()}>
      <div className="modal-header"><h2>{title}</h2><button className="close" onClick={onClose}>×</button></div>
      {children}
    </div>
  </div>;
}

export function FormField({ label, children, full = false }) {
  return <label className={`form-field ${full ? "full" : ""}`}><span>{label}</span>{children}</label>;
}
