import json
from collections import defaultdict

# Load semua JSON
def load_json(file):
    with open(file) as f:
        return json.load(f)

players = load_json('players.json')
player_teams = load_json('players_teams.json')
player_allstar = load_json('player_allstar.json')
award_players = load_json('awards_players.json')
coach = load_json('coaches.json')
award_coaches = load_json('awards_coaches.json')
draft = load_json('draft.json')

# Mapping data pemain
teams_by_player = defaultdict(list)
allstars_by_player = defaultdict(list)
awards_by_player = defaultdict(list)
draft_by_player = {}

for pt in player_teams:
    teams_by_player[pt["playerID"]].append(pt)

for a in player_allstar:
    allstars_by_player[a["playerID"]].append(a)

for a in award_players:
    awards_by_player[a["playerID"]].append(a)

for d in draft:
    draft_by_player[d["playerID"]] = d

# Gabungkan data pemain
merged_players = []

for p in players:
    player_id = p["playerID"]
    merged = {
        **p,
        "career_teams": teams_by_player.get(player_id, []),
        "allstar_games": allstars_by_player.get(player_id, []),
        "player_awards": awards_by_player.get(player_id, []),
        "draft_info": draft_by_player.get(player_id, None)
    }
    merged_players.append(merged)

# Mapping data pelatih
teams_by_coach = defaultdict(list)
awards_by_coach = defaultdict(list)

for c in coach:
    teams_by_coach[c["coachID"]].append(c)

for a in award_coaches:
    awards_by_coach[a["coachID"]].append(a)

# Gabungkan data pelatih
merged_coaches = []
all_coach_ids = set(list(teams_by_coach.keys()) + list(awards_by_coach.keys()))

for cid in all_coach_ids:
    merged = {
        "coachID": cid,
        "teams": teams_by_coach.get(cid, []),
        "coach_awards": awards_by_coach.get(cid, [])
    }
    merged_coaches.append(merged)

# Simpan dua file terpisah
with open("players_merged.json", "w") as f:
    json.dump(merged_players, f, indent=2)

with open("coaches_merged.json", "w") as f:
    json.dump(merged_coaches, f, indent=2)
