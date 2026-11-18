"""
Genetic calculator specific to Columba livia domestica (carrier pigeon).
Key differences from psittacines:
- ZW sex determination (females = ZW, males = ZZ)
- Color and dilute are co-sex-linked on Z chromosome (haplotypes)
- Pattern: 4-allele dominance series (T-check > checker > bar > barless)
- Spread epistatic to pattern
- Recessive red epistatic to pattern when homozygous
- Slipper: incomplete dominant (SF intermediate, DF maximum)
- Grouse: autosomal recessive, synergistic with slipper (Muff = DF + grouse/grouse)
"""
from dataclasses import dataclass
from typing import Optional, List, Dict, Tuple


@dataclass
class PigeonGenotypeProfile:
    sex: str  # "M" | "F"
    color_z1: Optional[str] = None   # ash_red|blue|brown
    color_z2: Optional[str] = None   # male only
    dilute_z1: Optional[str] = None  # dilute|not_dilute
    dilute_z2: Optional[str] = None  # male only
    pattern_a1: Optional[str] = None # T_check|checker|bar|barless
    pattern_a2: Optional[str] = None
    spread_a1: Optional[str] = None  # spread|not_spread
    spread_a2: Optional[str] = None
    rec_red_a1: Optional[str] = None # rec_red|not_rec_red
    rec_red_a2: Optional[str] = None
    crest_a1: Optional[str] = None   # crest|no_crest
    crest_a2: Optional[str] = None
    slipper_a1: Optional[str] = None # slipper|no_slipper
    slipper_a2: Optional[str] = None
    grouse_a1: Optional[str] = None  # grouse|no_grouse
    grouse_a2: Optional[str] = None


COLOR_HIERARCHY = ["ash_red", "blue", "brown"]
PATTERN_HIERARCHY = ["T_check", "checker", "bar", "barless"]
RECOMBINATION_COLOR_DILUTE = 0.14  # 14% crossover in males


def _dominant(a1: str, a2: Optional[str], hierarchy: List[str]) -> str:
    for a in hierarchy:
        if a == a1 or a == a2:
            return a
    return a1


class PigeonGeneticCalculator:

    def calculate(self, sire: PigeonGenotypeProfile, dam: PigeonGenotypeProfile) -> Tuple[List[Dict], List[Dict]]:
        results = []
        warnings = []

        # Clinical alerts
        if (sire.pattern_a1 == sire.pattern_a2 == "barless" or
                dam.pattern_a1 == dam.pattern_a2 == "barless"):
            warnings.append({
                "type": "clinical_alert",
                "locus": "pattern",
                "severity": "attention",
                "message": (
                    "Cruzamento com barless pode gerar descendentes homozigóticos "
                    "com maior incidência de defeitos visuais. Informar ao PigeonVet."
                ),
            })

        # Generate Z haplotypes for offspring
        z_combos_son, z_combos_daughter = self._z_haplotype_offspring(sire, dam)

        # Autosomal loci combinations
        pattern_combos = self._autosomal_combo(sire.pattern_a1, sire.pattern_a2, dam.pattern_a1, dam.pattern_a2)
        spread_combos = self._autosomal_combo(sire.spread_a1, sire.spread_a2, dam.spread_a1, dam.spread_a2)
        rec_red_combos = self._autosomal_combo(sire.rec_red_a1, sire.rec_red_a2, dam.rec_red_a1, dam.rec_red_a2)
        crest_combos = self._autosomal_combo(sire.crest_a1, sire.crest_a2, dam.crest_a1, dam.crest_a2)
        slipper_combos = self._autosomal_combo(sire.slipper_a1, sire.slipper_a2, dam.slipper_a1, dam.slipper_a2)
        grouse_combos = self._autosomal_combo(sire.grouse_a1, sire.grouse_a2, dam.grouse_a1, dam.grouse_a2)

        from itertools import product as iprod

        for sex, z_combos in [("M", z_combos_son), ("F", z_combos_daughter)]:
            for (zc, zp, z_prob) in z_combos:
                for (pa1, pa2), pp in pattern_combos.items():
                    for (sa1, sa2), sp in spread_combos.items():
                        for (ra1, ra2), rp in rec_red_combos.items():
                            for (ca1, ca2), cp in crest_combos.items():
                                for (sl1, sl2), slp in slipper_combos.items():
                                    for (gr1, gr2), grp in grouse_combos.items():
                                        total_prob = z_prob * pp * sp * rp * cp * slp * grp
                                        if total_prob < 0.005:
                                            continue
                                        phenotype = self._build_phenotype(
                                            sex, zc, zp,
                                            pa1, pa2, sa1, sa2, ra1, ra2,
                                            ca1, ca2, sl1, sl2, gr1, gr2,
                                        )
                                        results.append({
                                            "sex": sex,
                                            "probability": round(total_prob * 100, 2),
                                            **phenotype,
                                        })

        # Merge duplicates
        merged: Dict[str, Dict] = {}
        for r in results:
            key = (r["sex"], r["color"], r["pattern"], r["spread"], r["dilute"],
                   r["crest"], r["foot_feathering"])
            if key in merged:
                merged[key]["probability"] = round(merged[key]["probability"] + r["probability"], 2)
            else:
                merged[key] = r.copy()

        final = sorted(merged.values(), key=lambda x: x["probability"], reverse=True)
        filtered = [r for r in final if r["probability"] >= 0.5]
        return filtered, warnings

    def _z_haplotype_offspring(
        self, sire: PigeonGenotypeProfile, dam: PigeonGenotypeProfile
    ) -> Tuple[List, List]:
        """
        Returns (sons, daughters) haplotype lists as [(color, dilute, prob), ...]
        Sons: get one Z from sire + one Z from dam.
        Daughters: get one Z from sire + W from dam (no Z from dam).
        """
        # Sire haplotypes (considering recombination between color and dilute)
        if sire.color_z2 is None:  # shouldn't happen for male but guard
            sire_haplotypes = [(sire.color_z1, sire.dilute_z1, 1.0)]
        else:
            r = RECOMBINATION_COLOR_DILUTE
            sire_haplotypes = [
                (sire.color_z1, sire.dilute_z1, (1 - r) / 2),
                (sire.color_z2, sire.dilute_z2, (1 - r) / 2),
                (sire.color_z1, sire.dilute_z2, r / 2),  # crossover
                (sire.color_z2, sire.dilute_z1, r / 2),  # crossover
            ]

        # Dam Z haplotype (single Z for ZW female)
        dam_z = (dam.color_z1, dam.dilute_z1)

        # Sons: sire_Z + dam_Z → ZZ
        sons = []
        for sc, sd, sp in sire_haplotypes:
            sons.append((
                _dominant(sc, dam_z[0], COLOR_HIERARCHY),
                "dilute" if ("dilute" in (sd, dam_z[1])) else "not_dilute",
                sp * 0.5,
            ))

        # Daughters: sire_Z + W → ZW
        daughters = []
        for sc, sd, sp in sire_haplotypes:
            daughters.append((sc, sd, sp * 0.5))

        return sons, daughters

    def _autosomal_combo(self, a1, a2, b1, b2) -> Dict[Tuple, float]:
        if not all([a1, a2, b1, b2]):
            return {(a1, a2): 1.0}
        combos = [(a1, b1), (a1, b2), (a2, b1), (a2, b2)]
        out = {}
        for x, y in combos:
            pair = tuple(sorted([x, y]))
            out[pair] = out.get(pair, 0) + 0.25
        return out

    def _build_phenotype(self, sex, color, dilute, pa1, pa2, sa1, sa2, ra1, ra2, ca1, ca2, sl1, sl2, gr1, gr2) -> Dict:
        color_phen = color
        if dilute == "dilute":
            color_phen = f"{color}_dilute"

        # Pattern
        spread = "spread" in (sa1, sa2)
        rec_red = (ra1 == ra2 == "rec_red")
        if spread or rec_red:
            pattern_phen = "hidden (spread)" if spread else "hidden (rec_red)"
        else:
            pattern_phen = _dominant(pa1, pa2, PATTERN_HIERARCHY)

        # Crest
        crest = (ca1 == ca2 == "crest")

        # Foot feathering
        foot = self._resolve_foot_feathering(sl1, sl2, gr1, gr2)

        return {
            "color": color_phen,
            "pattern": pattern_phen,
            "spread": spread,
            "dilute": dilute == "dilute",
            "crest": crest,
            "rec_red": rec_red,
            "foot_feathering": foot,
            "visual_description": f"({sex}) {color_phen} {pattern_phen}" + (" crested" if crest else ""),
        }

    def _resolve_foot_feathering(self, sl1, sl2, gr1, gr2) -> str:
        if not all([sl1, sl2, gr1, gr2]):
            return "unknown"
        slipper_df = (sl1 == sl2 == "slipper")
        slipper_sf = ("slipper" in (sl1, sl2)) and not slipper_df
        grouse_expr = (gr1 == gr2 == "grouse")
        if slipper_df and grouse_expr:
            return "muff"
        elif slipper_df:
            return "slipper_df"
        elif slipper_sf and grouse_expr:
            return "slipper_grouse"
        elif slipper_sf:
            return "slipper_sf"
        elif grouse_expr:
            return "grouse"
        return "none"
