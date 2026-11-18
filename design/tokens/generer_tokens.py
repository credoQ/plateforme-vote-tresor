# -*- coding: utf-8 -*-
"""Génère les tokens « Femme modèle du Trésor » à partir des tokens Kadoo 2.0,
en remplaçant la couleur primaire (vert Kadoo #046C4E) par le bleu Trésor.

Méthode de la charte Kadoo : l'échelle est obtenue par mélange de la couleur de base
vers le blanc (niveaux clairs) et vers un presque-noir de même teinte (niveaux foncés),
avec les mêmes proportions que l'échelle Kadoo. Les neutres sont teintés de la couleur
primaire (même luminosité et saturation que les gris Kadoo, teinte du bleu).
Couleurs de la charte graphique DGTCP : bleu Pantone 3015 C #0054A0 (primaire),
vert Pantone 364 C #2E5A21 (2e couleur des graphiques) ; accent or doré #E0A800 (remplace l'or Kadoo).
Usage : python generer_tokens.py <kadoo_tokens.json> <dossier_sortie> [#bleu] [#vert] [#or]"""
import json, sys, os, colorsys

SRC, OUT = sys.argv[1], sys.argv[2]
BASE = (sys.argv[3] if len(sys.argv) > 3 else "#0054A0").upper()
VERT_DGTCP = (sys.argv[4] if len(sys.argv) > 4 else "#2E5A21").upper()
OR_ACCENT = (sys.argv[5] if len(sys.argv) > 5 else "#E0A800").upper()
K = json.load(open(SRC, encoding="utf-8"))["couleurs"]
LEVELS = ["25", "50", "100", "200", "300", "400", "500", "600", "700", "800", "900", "950"]

def h2rgb(h): h = h.lstrip("#"); return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))
def rgb2h(c): return "#" + "".join(f"{max(0, min(255, round(v))):02X}" for v in c)
def mix(a, b, t): return tuple(a[i] * (1 - t) + b[i] * t for i in range(3))
def lum(c):
    def ch(v):
        v /= 255; return v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4
    r, g, b = (ch(v) for v in c); return 0.2126 * r + 0.7152 * g + 0.0722 * b
def contrast(a, b):
    la, lb = sorted([lum(h2rgb(a)), lum(h2rgb(b))], reverse=True); return (la + 0.05) / (lb + 0.05)

# 1. proportions de mélange de l'échelle Kadoo
kb = h2rgb(K["vert"]["600"]); W = (255, 255, 255)
kdark_target = mix(kb, (0, 0, 0), 0.85)          # presque-noir vert implicite
ratios = {}
for lv in LEVELS:
    c = h2rgb(K["vert"][lv]); i = LEVELS.index(lv)
    if i < 7:
        ratios[lv] = sum((c[j] - kb[j]) / (255 - kb[j]) for j in range(3)) / 3
    elif i > 7:
        ratios[lv] = sum((kb[j] - c[j]) / (kb[j] - kdark_target[j]) for j in range(3) if kb[j] > 10) / sum(1 for j in range(3) if kb[j] > 10)
    else:
        ratios[lv] = 0.0

# 2. échelle bleu Trésor
b = h2rgb(BASE); dark_target = mix(b, (0, 0, 0), 0.85)
bleu = {}
for lv in LEVELS:
    i = LEVELS.index(lv)
    bleu[lv] = BASE if i == 7 else rgb2h(mix(b, W, ratios[lv]) if i < 7 else mix(b, dark_target, ratios[lv]))

# 3. neutres teintés de bleu (même L et S que les gris Kadoo, teinte du bleu Trésor)
hb, lb_, sb = colorsys.rgb_to_hls(*(v / 255 for v in b))
gris = {}
for lv, hx in K["gris"].items():
    h, l, s = colorsys.rgb_to_hls(*(v / 255 for v in h2rgb(hx)))
    gris[lv] = rgb2h(tuple(v * 255 for v in colorsys.hls_to_rgb(hb, l, s)))

# 3 bis. échelle or (accent) : niveaux et proportions de l'or Kadoo, base au niveau 500
ko = {k: h2rgb(x) for k, x in K["or"].items()}; kob = ko["500"]; kodark = mix(kob, (0, 0, 0), 0.85)
ob = h2rgb(OR_ACCENT); odark = mix(ob, (0, 0, 0), 0.85); orr = {}
for lv, c in ko.items():
    canaux = [j for j in range(3) if (kob[j] < 250 if int(lv) < 500 else kob[j] > 10)]
    if lv == "500":
        orr[lv] = OR_ACCENT
    elif int(lv) < 500:
        t = sum((c[j] - kob[j]) / (255 - kob[j]) for j in canaux) / len(canaux); orr[lv] = rgb2h(mix(ob, W, t))
    else:
        t = sum((kob[j] - c[j]) / (kob[j] - kodark[j]) for j in canaux) / len(canaux); orr[lv] = rgb2h(mix(ob, odark, t))

def plus_proche_lisible(fond, seuil=3.0, ordre=("500", "300", "100", "50")):
    """Premier niveau d'or, dans l'ordre donné, qui atteint le seuil (usage non textuel)."""
    return next(lv for lv in ordre if contrast(orr[lv], fond) >= seuil)

# 4. thème sombre : primaire éclairci (niveau 400 environ, vérifié en contraste sur la surface sombre)
dark = {"fond": "#0B0F14", "surface": "#121820", "surface-2": "#161E28", "bordure": "#26303C", "bordure-legere": "#1C2530",
        "texte": gris["100"], "texte-2": "#A9B3BE", "texte-3": "#8A95A1"}
# éclaircissement minimal pour atteindre 4,5:1 sur la surface sombre (calculé, pas estimé)
t_prim = next(t / 100 for t in range(0, 100) if contrast(rgb2h(mix(b, W, t / 100)), dark["surface"]) >= 4.5)
dark_prim = rgb2h(mix(b, W, t_prim)); dark_prim_hover = rgb2h(mix(b, W, t_prim + 0.12)); dark_prim_text = rgb2h(mix(b, W, max(0.55, t_prim + 0.3)))

# 4 bis. accents : l'or 500 marque (menu actif, anneau, liseré du bouton, barre de la lauréate).
# Sur un fond qui ne lui laisse pas 3:1 (bouton primaire du thème sombre), on prend le premier niveau qui l'atteint.
acc_sur_prim = orr[plus_proche_lisible(bleu["600"])]
acc_sombre = orr[plus_proche_lisible(dark["surface"])]
acc_sur_prim_sombre = orr[plus_proche_lisible(dark_prim)]

# 5. graphiques : le rang 1 suit la primaire ; le bleu Kadoo (rang 2) devient le vert Kadoo
graph = [["Bleu Trésor", BASE], ["Vert DGTCP", VERT_DGTCP]] + [g for g in K["graphiques"][2:]]

palette = {"bleu": bleu, "gris": gris, "or": orr, "succes": K["succes"], "attente": K["attente"],
           "erreur": K["erreur"], "info": K["info"], "drapeau": K["drapeau"]}

# ---------------------------------------------------------------- écriture
os.makedirs(OUT, exist_ok=True)
L = ["/* Femme modèle du Trésor · Variables de conception · thèmes clair et sombre",
     f"   Généré depuis Kadoo 2.0 : primaire bleu Trésor {BASE} (charte DGTCP), accent or doré {OR_ACCENT}. */", ":root{"]
for fam, vals in palette.items():
    for lv, hx in vals.items(): L.append(f"  --tr-{fam}-{lv}:{hx};")
for i, (nom, hx) in enumerate(graph, 1): L.append(f"  --tr-graph-{i}:{hx}; /* {nom} */")
L.append("  --tr-graph-autres:" + gris["400"] + ";")
roles = {"fond": "var(--tr-gris-50)", "surface": "#FFFFFF", "surface-2": "var(--tr-gris-25)", "bordure": "var(--tr-gris-200)",
         "bordure-legere": "var(--tr-gris-100)", "texte": "var(--tr-gris-900)", "texte-2": "var(--tr-gris-600)", "texte-3": "var(--tr-gris-500)",
         "primaire": "var(--tr-bleu-600)", "primaire-survol": "var(--tr-bleu-700)", "primaire-doux": "var(--tr-bleu-50)",
         "primaire-texte": "var(--tr-bleu-700)", "sur-primaire": "#FFFFFF", "accent": "var(--tr-or-500)", "accent-sur-primaire": acc_sur_prim}
for k, v in roles.items(): L.append(f"  --tr-{k}:{v};")
L += ["  --tr-rayon-sm:6px;", "  --tr-rayon:8px;", "  --tr-rayon-carte:12px;", "  --tr-rayon-modale:14px;",
      "  --tr-ombre-1:0 1px 2px rgba(10,13,18,.06);", "  --tr-ombre-3:0 12px 32px -6px rgba(10,13,18,.18),0 4px 8px -4px rgba(10,13,18,.08);",
      "  --tr-sidebar:264px;", "  --tr-sidebar-repliee:80px;", "  --tr-entete:64px;", '  --tr-police:"Montserrat",sans-serif;', "}",
      '[data-theme="sombre"]{']
for k, v in dark.items(): L.append(f"  --tr-{k}:{v};")
L += [f"  --tr-primaire:{dark_prim};", f"  --tr-primaire-survol:{dark_prim_hover};",
      f"  --tr-primaire-doux:rgba({','.join(str(round(x)) for x in h2rgb(dark_prim))},.16);", f"  --tr-primaire-texte:{dark_prim_text};", f"  --tr-sur-primaire:{gris['950']};",
      f"  --tr-accent:{acc_sombre};", f"  --tr-accent-sur-primaire:{acc_sur_prim_sombre};", f"  --tr-graph-1:{dark_prim};", "}"]
open(os.path.join(OUT, "tresor-tokens.css"), "w", encoding="utf-8").write("\n".join(L) + "\n")

T = ["/* Femme modèle du Trésor · Thème Tailwind CSS 4 (dérivé de Kadoo 2.0, primaire bleu Trésor)",
     "   À importer après @import \"tailwindcss\" et après tresor-tokens.css.",
     "   Classes générées : bg-tresor-600, text-gris-900, text-libelle, rounded-carte, shadow-niveau-1, w-sidebar, max-w-contenu... */", "",
     "@custom-variant dark (&:where([data-theme=sombre], [data-theme=sombre] *));", "", "@theme {",
     '  --font-sans: "Montserrat", sans-serif;', ""]
for lv, hx in bleu.items(): T.append(f"  --color-tresor-{lv}: {hx};")
T.append("")
for fam in ["gris", "or", "succes", "attente", "erreur", "info"]:
    for lv, hx in palette[fam].items(): T.append(f"  --color-{fam}-{lv}: {hx};")
    T.append("")
for k, hx in K["drapeau"].items(): T.append(f"  --color-drapeau-{k}: {hx};")
for i, (nom, hx) in enumerate(graph, 1): T.append(f"  --color-graph-{i}: {hx};")
T += ["  --color-graph-autres: " + gris["400"] + ";", "",
      "  /* Styles de texte (charte Kadoo, section 3) */",
      "  --text-display: 32px; --text-display--line-height: 40px; --text-display--font-weight: 700;",
      "  --text-h1: 24px; --text-h1--line-height: 32px; --text-h1--font-weight: 700;",
      "  --text-h2: 18px; --text-h2--line-height: 26px; --text-h2--font-weight: 600;",
      "  --text-h3: 15px; --text-h3--line-height: 22px; --text-h3--font-weight: 600;",
      "  --text-corps: 14px; --text-corps--line-height: 22px;",
      "  --text-libelle: 13px; --text-libelle--line-height: 18px; --text-libelle--font-weight: 500;",
      "  --text-petit: 12px; --text-petit--line-height: 18px;",
      "  --text-surtitre: 11px; --text-surtitre--line-height: 16px; --text-surtitre--font-weight: 700; --text-surtitre--letter-spacing: 0.08em;", "",
      "  /* Rayons, ombres, structure */",
      "  --radius-etiquette: 6px; --radius-controle: 8px; --radius-carte: 12px; --radius-modale: 14px;",
      "  --shadow-niveau-1: 0 1px 2px rgba(10, 13, 18, .06);",
      "  --shadow-niveau-3: 0 12px 32px -6px rgba(10, 13, 18, .18), 0 4px 8px -4px rgba(10, 13, 18, .08);",
      f"  --shadow-lisere: inset 3px 0 0 {acc_sur_prim};",
      "  --spacing-sidebar: 264px; --spacing-sidebar-repliee: 80px; --spacing-entete: 64px;",
      "  --container-contenu: 1440px;", "}", "",
      "/* Rôles : suivent le thème clair ou sombre défini dans tresor-tokens.css */", "@theme inline {"]
for k in roles: T.append(f"  --color-{k}: var(--tr-{k});")
T.append("}")
open(os.path.join(OUT, "tresor-theme.css"), "w", encoding="utf-8").write("\n".join(T) + "\n")

J = {"source": "Kadoo 2.0", "primaire": {"nom": "Bleu Trésor (Pantone 3015 C)", "base": BASE, "remplace": K["vert"]["600"]},
     "accent": {"nom": "Or doré", "base": OR_ACCENT, "remplace": K["or"]["500"], "sur_primaire": acc_sur_prim},
     "vert_institutionnel": {"nom": "Vert Trésor (Pantone 364 C)", "base": VERT_DGTCP, "usage": "graphiques"},
     "couleurs": {**palette, "graphiques": graph}, "theme_sombre": {**dark, "primaire": dark_prim, "primaire_survol": dark_prim_hover, "primaire_texte": dark_prim_text},
     "police": "Montserrat", "icones": "Lucide", "rayons": {"sm": 6, "defaut": 8, "carte": 12, "modale": 14},
     "espacement_base": 4, "structure": {"sidebar": 264, "sidebar_repliee": 80, "entete": 64, "contenu_max": 1440}}
open(os.path.join(OUT, "tresor-tokens.json"), "w", encoding="utf-8").write(json.dumps(J, ensure_ascii=False, indent=1))

# ---------------------------------------------------------------- contrôle des contrastes
checks = [("primaire 600 / blanc (bouton, lien)", bleu["600"], "#FFFFFF", 4.5), ("primaire-texte 700 / blanc", bleu["700"], "#FFFFFF", 4.5),
          ("primaire-texte 700 / primaire-doux 50", bleu["700"], bleu["50"], 4.5), ("blanc / primaire-survol 700", "#FFFFFF", bleu["700"], 4.5),
          ("texte gris 900 / fond gris 50", gris["900"], gris["50"], 4.5), ("texte-2 gris 600 / blanc", gris["600"], "#FFFFFF", 4.5),
          ("texte-3 gris 500 / blanc (placeholder)", gris["500"], "#FFFFFF", 4.5), ("blanc / tuile héros 900", "#FFFFFF", bleu["900"], 4.5),
          ("sombre : primaire / surface", dark_prim, dark["surface"], 4.5), ("sombre : texte gris 950 / bouton primaire", gris["950"], dark_prim, 4.5), ("sombre : primaire-texte / surface", dark_prim_text, dark["surface"], 4.5),
          ("sombre : texte / fond", dark["texte"], dark["fond"], 4.5),
          ("badge accent : or 700 / or 50", orr["700"], orr["50"], 4.5), ("texte gris 900 / or 500", gris["900"], OR_ACCENT, 4.5),
          ("liseré or / bouton primaire (non textuel)", acc_sur_prim, bleu["600"], 3.0), ("sombre : accent / surface", acc_sombre, dark["surface"], 3.0),
          ("sombre : liseré / bouton primaire", acc_sur_prim_sombre, dark_prim, 3.0)]
print("Échelle bleu Trésor :", " ".join(f"{k}={v}" for k, v in bleu.items()))
print("Gris :", " ".join(f"{k}={v}" for k, v in gris.items()))
print("Or :", " ".join(f"{k}={x}" for k, x in orr.items()))
print(f"Repère décoratif or 500 sur blanc : {contrast(OR_ACCENT, '#FFFFFF'):.2f}:1 (non textuel, comme l'or Kadoo)")
print("Accent sur primaire :", acc_sur_prim, "· sombre :", acc_sombre, "· liseré sombre :", acc_sur_prim_sombre)
for nom, a, c, seuil in checks:
    r = contrast(a, c); print(f"  {nom:42s} {r:5.2f}:1 {'AA' if r >= seuil else 'ÉCHEC'}{' AAA' if r >= 7 else ''}")
