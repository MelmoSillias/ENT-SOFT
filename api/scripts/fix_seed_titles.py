#!/usr/bin/env python3
"""Fix project titles encoding in the Telecel seed and apply to local SQLite DB."""
from pathlib import Path
import re
import sqlite3

SEED = Path(r"C:\Users\DELL\Desktop\Programmation\Web\ENT-SOFT\api\migrations\seeds\seed_telecel_planning.sql")
DB = Path(r"C:\Users\DELL\Desktop\Programmation\Web\ENT-SOFT\api\var\data_dev.db")

titles = {
    "PRJ-ATEL-SOLAIRE": "Déploiement solaire ATEL",
    "PRJ-PV-13SITES": "Installation de pv juillet 2026 - août 2026",
    "PRJ-PV-22SITES": "Installation de pv août 2026 - août 2026",
    "PRJ-SURVEY-ATEL": "Survey mise à la terre",
}

c = SEED.read_text(encoding="utf-8", errors="replace")
c = re.sub(r"D.ploiement solaire ATEL", titles["PRJ-ATEL-SOLAIRE"], c)
c = re.sub(r"ao.t 2026", "août 2026", c)
c = re.sub(r"mise . la terre", "mise à la terre", c)
SEED.write_text(c, encoding="utf-8")

if DB.exists():
    conn = sqlite3.connect(DB)
    try:
        for code, title in titles.items():
            conn.execute("UPDATE projects SET title = ? WHERE code = ?", (title, code))
        conn.commit()
        rows = conn.execute("SELECT code, title FROM projects ORDER BY code").fetchall()
        for code, title in rows:
            print(code, "->", title)
    finally:
        conn.close()
else:
    print(f"DB not found at {DB}; seed file updated only.")

print("titles fixed")
