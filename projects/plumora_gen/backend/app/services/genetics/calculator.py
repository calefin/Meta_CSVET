"""
Genetic calculator for psittacines and canaries.
Supports: autosomal recessive (re), sex-linked recessive (se),
autosomal dominant (do), incomplete dominant (in), codominant (cd),
multi-allelic series, and crossover in linked loci.
"""
from enum import Enum
from dataclasses import dataclass, field
from typing import Optional, List, Dict, Tuple, Any
from itertools import product as cartesian_product
from fractions import Fraction


class InheritanceMode(str, Enum):
    AUTOSOMAL_RECESSIVE = "re"
    SEX_LINKED_RECESSIVE = "se"
    AUTOSOMAL_DOMINANT = "do"
    INCOMPLETE_DOMINANT = "in"
    CODOMINANT = "cd"


@dataclass
class LocusDefinition:
    symbol: str
    mode: InheritanceMode
    alleles_json: Optional[List[Dict]] = None
    crossover_freq: float = 0.0
    is_multi_allele: bool = False


@dataclass
class BirdGeneticProfile:
    sex: str  # "M" | "F"
    # {symbol: (allele_1, allele_2)} — allele_2 = None for sex-linked in females
    loci: Dict[str, Tuple[str, Optional[str]]] = field(default_factory=dict)


def _dominant_of(a1: str, a2: str, hierarchy: List[str]) -> str:
    for a in hierarchy:
        if a in (a1, a2):
            return a
    return a1


class GeneticCalculator:
    """
    Multi-loci genetic probability calculator.
    """

    def __init__(self, db=None):
        self.db = db

    def calculate_from_birds(
        self,
        male_bird,
        female_bird,
        include_sexes: bool = True,
        min_probability: float = 1.0,
    ) -> Tuple[List[Dict], List[Dict]]:
        from app.models.genetics import BirdGenotype, GeneticLocus

        if not self.db:
            return [], []

        def load_profile(bird) -> BirdGeneticProfile:
            genotypes = self.db.query(BirdGenotype).filter(BirdGenotype.bird_id == bird.id).all()
            loci = {}
            for g in genotypes:
                loci[g.locus.symbol] = (g.allele_1, g.allele_2)
            return BirdGeneticProfile(sex=bird.sex, loci=loci)

        # Load locus definitions for the species
        species_id = male_bird.species_id
        locus_defs = {}
        if species_id:
            defs = self.db.query(GeneticLocus).filter(GeneticLocus.species_id == species_id).all()
            for d in defs:
                locus_defs[d.symbol] = LocusDefinition(
                    symbol=d.symbol,
                    mode=InheritanceMode(d.inheritance_mode),
                    alleles_json=d.alleles_json,
                    crossover_freq=d.crossover_freq or 0.0,
                    is_multi_allele=d.is_multi_allele or False,
                )

        male_profile = load_profile(male_bird)
        female_profile = load_profile(female_bird)
        return self.calculate(male_profile, female_profile, list(locus_defs.values()), min_probability, include_sexes)

    def calculate(
        self,
        male: BirdGeneticProfile,
        female: BirdGeneticProfile,
        loci_definitions: List[LocusDefinition],
        min_probability: float = 1.0,
        include_sexes: bool = True,
    ) -> Tuple[List[Dict], List[Dict]]:
        warnings = []
        loci_results = []

        for locus_def in loci_definitions:
            sym = locus_def.symbol
            m_alleles = male.loci.get(sym)
            f_alleles = female.loci.get(sym)

            if m_alleles is None or f_alleles is None:
                warnings.append({"locus": sym, "message": f"Locus '{sym}' not defined — skipping"})
                continue

            if locus_def.is_multi_allele and locus_def.alleles_json:
                # Multi-allele series must be checked before the plain re/se/do/in
                # branches below — those only know a binary V/+ (or DF/SF) genotype
                # and would otherwise silently swallow the extra alleles.
                hierarchy = [a["symbol"] for a in sorted(locus_def.alleles_json, key=lambda x: x.get("rank", 99))]
                if any(a.get("sub_mode") for a in locus_def.alleles_json):
                    warnings.append({
                        "locus": sym,
                        "message": f"Locus '{sym}' mistura alelos dominantes e recessivos (sub_mode) — cálculo aproximado, trata todos os alelos mutantes como recessivos entre si.",
                    })
                if locus_def.mode == InheritanceMode.SEX_LINKED_RECESSIVE:
                    result = self._punnett_multi_allele_se(m_alleles, f_alleles[0], sym, hierarchy)
                else:
                    result = self._punnett_multi_allele(m_alleles, f_alleles, sym, hierarchy)
            elif locus_def.mode == InheritanceMode.AUTOSOMAL_RECESSIVE:
                result = self._punnett_re(m_alleles, f_alleles, sym)
            elif locus_def.mode == InheritanceMode.SEX_LINKED_RECESSIVE:
                result = self._punnett_se(m_alleles, f_alleles[0], sym, locus_def.crossover_freq)
            elif locus_def.mode in (InheritanceMode.AUTOSOMAL_DOMINANT, InheritanceMode.INCOMPLETE_DOMINANT):
                result = self._punnett_dominant(m_alleles, f_alleles, sym, locus_def.mode)
            else:
                result = self._punnett_re(m_alleles, f_alleles, sym)

            loci_results.append(result)

        combined = self._combine_loci(loci_results, include_sexes)
        filtered = [r for r in combined if r["probability"] >= min_probability]
        filtered.sort(key=lambda x: x["probability"], reverse=True)
        return filtered, warnings

    def _punnett_re(self, m: Tuple, f: Tuple, sym: str) -> Dict:
        """Autosomal recessive: AA=not_carrier, Aa=split, aa=visual"""
        m1, m2 = m[0], m[1] or m[0]
        f1, f2 = f[0], f[1] or f[0]

        combos = [(m1, f1), (m1, f2), (m2, f1), (m2, f2)]
        results = {}
        for a, b in combos:
            pair = tuple(sorted([a, b]))
            results[pair] = results.get(pair, 0) + 1

        out = {}
        for pair, count in results.items():
            prob = count / 4
            if pair[0] == "V" and pair[1] == "V":
                phenotype = "visual"
                genotype = f"{sym}/visual"
            elif "V" in pair:
                phenotype = "split"
                genotype = f"{sym}/split"
            else:
                phenotype = "not_carrier"
                genotype = f"+/+"
            key = (phenotype, genotype)
            out[key] = out.get(key, 0) + prob

        return {"locus": sym, "mode": "re", "outcomes": out}

    def _punnett_se(self, m: Tuple, f_allele: str, sym: str, crossover: float = 0.0) -> Dict:
        """Sex-linked recessive. Males ZZ, females ZW."""
        m1, m2 = m[0], m[1] or m[0]
        # Sons get one Z from dad + Z from mom (f_allele)
        # Daughters get one Z from dad + W from mom
        out = {}
        for dad_allele in [m1, m2]:
            # Son: dad_allele + f_allele (ZZ)
            son_pair = tuple(sorted([dad_allele, f_allele]))
            if son_pair[0] == "V" and son_pair[1] == "V":
                phen = "visual"
            elif "V" in son_pair:
                phen = "split"
            else:
                phen = "not_carrier"
            key = ("M", phen, sym)
            out[key] = out.get(key, 0) + 0.25

            # Daughter: dad_allele (ZW — only one Z)
            if dad_allele == "V":
                d_phen = "visual"
            else:
                d_phen = "not_carrier"
            dkey = ("F", d_phen, sym)
            out[dkey] = out.get(dkey, 0) + 0.25

        return {"locus": sym, "mode": "se", "outcomes": out}

    def _punnett_dominant(self, m: Tuple, f: Tuple, sym: str, mode: InheritanceMode) -> Dict:
        m1, m2 = m[0], m[1] or m[0]
        f1, f2 = f[0], f[1] or f[0]
        combos = [(m1, f1), (m1, f2), (m2, f1), (m2, f2)]
        out = {}
        for a, b in combos:
            if a == "DF" or b == "DF":
                phen = "DF" if mode == InheritanceMode.INCOMPLETE_DOMINANT else "visual"
            elif a == "SF" or b == "SF":
                phen = "SF" if mode == InheritanceMode.INCOMPLETE_DOMINANT else "visual"
            else:
                phen = "not_carrier"
            key = phen
            out[key] = out.get(key, 0) + 0.25
        return {"locus": sym, "mode": mode.value, "outcomes": {(k, k): v for k, v in out.items()}}

    def _classify_multi_allele(self, a: str, b: str, hierarchy: List[str]) -> Tuple[str, str]:
        """Resolve (phenotype, genotype_label) for one allele pair of a recessive
        multi-allele series. Wild-type '+' is masked whenever the other allele
        is any named mutant (split, invisible). Once *both* alleles are named
        mutants — same or different — the wild-type product is entirely absent,
        so the bird is always visual; `hierarchy` (rank order) only decides
        which mutant name is reported for a compound heterozygote."""
        if a == "+" and b == "+":
            return "not_carrier", "+/+"
        if a == "+" or b == "+":
            other = b if a == "+" else a
            return "split", f"{other}/split"
        winner = _dominant_of(a, b, hierarchy)
        return "visual", f"{winner}/visual"

    def _punnett_multi_allele(self, m: Tuple, f: Tuple, sym: str, hierarchy: List[str]) -> Dict:
        m1, m2 = m[0], m[1] or m[0]
        f1, f2 = f[0], f[1] or f[0]
        combos = [(m1, f1), (m1, f2), (m2, f1), (m2, f2)]
        out = {}
        for a, b in combos:
            key = self._classify_multi_allele(a, b, hierarchy)
            out[key] = out.get(key, 0) + 0.25
        return {"locus": sym, "mode": "re", "outcomes": out}

    def _punnett_multi_allele_se(self, m: Tuple, f_allele: str, sym: str, hierarchy: List[str]) -> Dict:
        """Sex-linked multi-allele series. Males ZZ, females ZW (hemizygous —
        any single mutant allele is fully expressed, there is no 'split' sex)."""
        m1, m2 = m[0], m[1] or m[0]
        out = {}
        for dad_allele in [m1, m2]:
            phen, _geno = self._classify_multi_allele(dad_allele, f_allele, hierarchy)
            key = ("M", phen, sym)
            out[key] = out.get(key, 0) + 0.25

            d_phen = "not_carrier" if dad_allele == "+" else "visual"
            dkey = ("F", d_phen, sym)
            out[dkey] = out.get(dkey, 0) + 0.25

        return {"locus": sym, "mode": "se", "outcomes": out}

    def _combine_loci(self, loci_results: List[Dict], include_sexes: bool) -> List[Dict]:
        if not loci_results:
            return []

        # Build list of [(key, prob)] per locus
        loci_options = []
        for lr in loci_results:
            options = [(k, v) for k, v in lr["outcomes"].items()]
            loci_options.append((lr["locus"], lr["mode"], options))

        # Cartesian product
        all_keys = [opts for _, _, opts in loci_options]
        results = []
        for combo in cartesian_product(*all_keys):
            prob = 1.0
            phenotypes = []
            splits = []
            geno_parts = []
            sex = None

            for i, (key, p) in enumerate(combo):
                prob *= p
                mode = loci_options[i][1]
                locus = loci_options[i][0]

                if mode == "se" and isinstance(key, tuple) and len(key) == 3:
                    sex_marker, phen, sym = key
                    if sex is None:
                        sex = sex_marker
                    elif sex != sex_marker:
                        prob = 0
                        break
                    if phen == "visual":
                        phenotypes.append(locus)
                    elif phen == "split":
                        splits.append(locus)
                else:
                    if isinstance(key, tuple) and len(key) >= 1:
                        phen = key[0]
                        geno = key[1] if len(key) > 1 else ""
                    else:
                        phen = str(key)
                        geno = ""
                    if phen == "visual":
                        phenotypes.append(locus)
                    elif phen == "split":
                        splits.append(locus)
                    if geno:
                        geno_parts.append(geno)

            if prob <= 0:
                continue

            sexes = [sex] if sex else (["M", "F"] if include_sexes else ["M/F"])
            for s in sexes:
                results.append({
                    "sex": s,
                    "phenotype": phenotypes,
                    "splits": splits,
                    "genotype_code": " ".join(geno_parts),
                    "probability": round(prob * 100 / len(sexes), 2),
                    "visual_description": self._describe(phenotypes, splits, s),
                })

        # Merge duplicates
        merged: Dict[str, Dict] = {}
        for r in results:
            key = (r["sex"], tuple(sorted(r["phenotype"])), tuple(sorted(r["splits"])))
            if key in merged:
                merged[key]["probability"] = round(merged[key]["probability"] + r["probability"], 2)
            else:
                merged[key] = r.copy()

        return list(merged.values())

    def _describe(self, phenotypes: List[str], splits: List[str], sex: str) -> str:
        parts = phenotypes[:]
        if splits:
            parts.append("split " + " / ".join(splits))
        desc = " ".join(parts) if parts else "Normal"
        return f"({sex}) {desc}"
