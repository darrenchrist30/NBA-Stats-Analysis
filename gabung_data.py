import json
from collections import defaultdict

# Fungsi untuk memuat JSON dari file
def load_json_from_file(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            data = json.load(f)
            if not isinstance(data, list):
                print(f"Peringatan: Isi dari {filepath} bukan list. Mengasumsikan satu objek akan dibungkus dalam list.")
                if isinstance(data, dict): return [data]
                print(f"   -> Format tidak dikenal sebagai list di {filepath}. Mengembalikan list kosong.")
                return []
            print(f"Berhasil memuat {len(data)} item dari {filepath}.")
            return data
    except FileNotFoundError:
        print(f"ERROR: File {filepath} tidak ditemukan.")
        return []
    except json.JSONDecodeError as e:
        print(f"ERROR: File {filepath} bukan format JSON yang valid. Kesalahan: {e}")
        return []
    except Exception as e:
        print(f"ERROR saat memuat {filepath}: {e}")
        return []

# --- 1. Load Semua Data JSON ---
print("Memulai proses pemuatan data...")
players_data = load_json_from_file('players.json')
player_teams_data = load_json_from_file('players_teams.json')
player_allstar_data = load_json_from_file('player_allstar.json')
awards_players_data = load_json_from_file('awards_players.json')
coaches_stints_data = load_json_from_file('coaches.json')
awards_coaches_data = load_json_from_file('awards_coaches.json')
draft_data = load_json_from_file('draft.json')
teams_annual_stats_list = load_json_from_file('teams.json') # List dari objek tim per musim
playoff_series_list = load_json_from_file('series_post.json') # List dari objek hasil seri playoff
print("Pemuatan data selesai.\n")


# --- BAGIAN PEMAIN (Tetap sama) ---
# ... (Salin kode untuk memproses dan menggabungkan data pemain dari skrip sebelumnya) ...
# Output: merged_players_list
print("Memproses data pemain...")
teams_by_player_id = defaultdict(list)
allstars_by_player_id = defaultdict(list)
awards_by_player_id = defaultdict(list)
draft_info_by_player_id = {}
for pt_entry in player_teams_data:
    player_id = pt_entry.get("playerID")
    if player_id: teams_by_player_id[player_id].append(pt_entry)
for allstar_entry in player_allstar_data:
    player_id = allstar_entry.get("playerID")
    if player_id: allstars_by_player_id[player_id].append(allstar_entry)
for award_entry in awards_players_data:
    player_id = award_entry.get("playerID")
    if player_id: awards_by_player_id[player_id].append(award_entry)
for draft_entry in draft_data:
    player_id = draft_entry.get("playerID")
    if player_id: draft_info_by_player_id[player_id] = draft_entry
merged_players_list = []
for p_info in players_data:
    player_id = p_info.get("playerID")
    if not player_id: continue
    merged_players_list.append({
        **p_info,
        "career_teams": teams_by_player_id.get(player_id, []),
        "allstar_games": allstars_by_player_id.get(player_id, []),
        "player_awards": awards_by_player_id.get(player_id, []),
        "draft_info": draft_info_by_player_id.get(player_id, None)
    })
print(f"Penggabungan data untuk {len(merged_players_list)} pemain selesai.\n")


# --- BAGIAN PELATIH (Disesuaikan untuk menyematkan teams.json dan series_post.json) ---
print("Memproses data pelatih dengan penyematan data tim dan playoff...")

# 1. Pre-processing data tim dan seri playoff untuk lookup yang efisien
#    Kelompokkan berdasarkan tahun dan tmID
teams_annual_lookup = defaultdict(dict) # teams_annual_lookup[year][tmID] = team_details
for team_stat in teams_annual_stats_list:
    year = team_stat.get('year')
    tmID = team_stat.get('tmID')
    if year is not None and tmID:
        teams_annual_lookup[year][tmID] = team_stat

playoff_series_lookup = defaultdict(lambda: defaultdict(list)) # playoff_series_lookup[year][tmID] = [series1, series2]
for series in playoff_series_list:
    year = series.get('year')
    winner_tmID = series.get('tmIDWinner')
    loser_tmID = series.get('tmIDLoser')
    if year is not None:
        if winner_tmID:
            playoff_series_lookup[year][winner_tmID].append(series)
        if loser_tmID: # Tim yang kalah juga berpartisipasi
            playoff_series_lookup[year][loser_tmID].append(series)


# 2. Kelompokkan stint kepelatihan dan penghargaan seperti sebelumnya
stints_by_coach_id = defaultdict(list)
for coach_stint in coaches_stints_data:
    coach_id = coach_stint.get("coachID")
    if coach_id:
        stints_by_coach_id[coach_id].append(coach_stint)

coach_awards_by_coach_id = defaultdict(list)
for coach_award in awards_coaches_data:
    coach_id = coach_award.get("coachID")
    if coach_id:
        coach_awards_by_coach_id[coach_id].append(coach_award)

all_unique_coach_ids = set(stints_by_coach_id.keys()).union(set(coach_awards_by_coach_id.keys()))

# 3. Gabungkan data untuk setiap pelatih, menyematkan detail tim dan playoff ke setiap stint
merged_coaches_list_enriched = []
for c_id in sorted(list(all_unique_coach_ids)):
    
    enriched_stints_for_coach = []
    original_stints = stints_by_coach_id.get(c_id, [])
    
    for stint in original_stints:
        year = stint.get('year')
        tmID = stint.get('tmID')
        
        # Buat salinan stint agar tidak memodifikasi data asli di stints_by_coach_id
        current_stint_enriched = stint.copy() 
        
        # Cari dan sematkan detail tim dari teams.json
        if year is not None and tmID:
            current_stint_enriched["team_season_details"] = teams_annual_lookup.get(year, {}).get(tmID, None) # None jika tidak ditemukan
        else:
            current_stint_enriched["team_season_details"] = None
            
        # Cari dan sematkan hasil seri playoff dari series_post.json
        if year is not None and tmID:
            current_stint_enriched["playoff_series_for_team"] = playoff_series_lookup.get(year, {}).get(tmID, []) # List kosong jika tidak ditemukan
        else:
            current_stint_enriched["playoff_series_for_team"] = []
            
        enriched_stints_for_coach.append(current_stint_enriched)
        
    coach_record = {
        "coachID": c_id,
        "teams": enriched_stints_for_coach, # Gunakan stint yang sudah diperkaya
        "coach_awards": coach_awards_by_coach_id.get(c_id, [])
    }
    merged_coaches_list_enriched.append(coach_record)
print(f"Penggabungan dan penyematan data untuk {len(merged_coaches_list_enriched)} pelatih selesai.\n")


# --- MENYIMPAN HASIL ---
# Anda bisa memilih untuk menyimpan file terpisah atau satu file gabungan besar.
# Karena permintaan Anda adalah format ulang coaches_merged, saya akan fokus pada itu.

# 1. Simpan data pemain yang digabungkan (jika masih perlu file terpisah)
output_players_filename = "players_merged_with_all.json" # Nama baru untuk membedakan
try:
    with open(output_players_filename, "w", encoding='utf-8') as f:
        json.dump(merged_players_list, f, indent=2, ensure_ascii=False)
    print(f"Data pemain berhasil digabungkan dan disimpan ke: {output_players_filename}")
except Exception as e:
    print(f"ERROR saat menyimpan {output_players_filename}: {e}")

# 2. Simpan data pelatih yang sudah diperkaya
output_coaches_enriched_filename = "coaches_merged_enriched.json"
try:
    with open(output_coaches_enriched_filename, "w", encoding='utf-8') as f:
        json.dump(merged_coaches_list_enriched, f, indent=2, ensure_ascii=False)
    print(f"Data pelatih yang diperkaya berhasil disimpan ke: {output_coaches_enriched_filename}")
except Exception as e:
    print(f"ERROR saat menyimpan {output_coaches_enriched_filename}: {e}")

# Opsi: Jika Anda ingin SATU file besar dengan semua data
# final_output_all_data = {
#     "players": merged_players_list,
#     "coaches": merged_coaches_list_enriched,
#     # Anda bisa memilih untuk menyimpan teams_annual_stats_list dan playoff_series_list
#     # secara mentah di sini juga jika diperlukan untuk referensi,
#     # meskipun sebagian datanya sudah disematkan ke pelatih.
#     "raw_teams_annual_stats": teams_annual_stats_list,
#     "raw_playoff_series": playoff_series_list
# }
# output_ultimate_combined_filename = "nba_ultimate_dataset.json"
# try:
#     with open(output_ultimate_combined_filename, "w", encoding='utf-8') as f:
#         json.dump(final_output_all_data, f, indent=2, ensure_ascii=False)
#     print(f"Semua data (termasuk mentah) disimpan ke: {output_ultimate_combined_filename}")
# except Exception as e:
#     print(f"ERROR saat menyimpan file ultimate: {e}")


print("\nProses selesai.")