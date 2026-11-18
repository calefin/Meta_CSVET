import data from '../../../shared/npcs.json';

export interface NpcInfo {
  city: string;
  area?: string;
  /** Rashid / Yasir: location comes from the schedule or the user toggle */
  travelling?: boolean;
}

const NPCS = (data as { npcs: Record<string, NpcInfo> }).npcs;

export function npcInfo(name: string): NpcInfo | undefined {
  return NPCS[name];
}

/** "Nah'Bob" → "Ashta'daramai, Darashia"; "Fiona" → "Edron" */
export function npcPlace(name: string): string {
  const n = NPCS[name];
  if (!n || n.travelling) return '';
  return n.area ? `${n.area}, ${n.city}` : n.city;
}
