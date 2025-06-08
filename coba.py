import json
import pandas as pd
from collections import Counter

# Fungsi untuk memuat JSON (sama seperti sebelumnya)
def load_json_to_df(filepath, record_path=None, meta=None, error_bad_lines=False, encoding='utf-8'):
    """
    Memuat file JSON ke dalam DataFrame Pandas.
    record_path, meta, error_bad_lines adalah parameter untuk json_normalize jika JSON bersarang.
    Jika JSON adalah list of flat dicts, json_normalize tidak diperlukan.
    """
    try:
        with open(filepath, 'r', encoding=encoding) as f:
            data = json.load(f)
        
        # Jika data adalah list dari dictionary (struktur JSON paling umum untuk tabel)
        if isinstance(data, list) and all(isinstance(item, dict) for item in data):
            df = pd.DataFrame(data)
            print(f"Berhasil memuat {filepath} ke DataFrame dengan {len(df)} baris.")
            return df
        # Jika data adalah dictionary (misalnya, JSON gabungan besar)
        elif isinstance(data, dict) and record_path:
            # Cek apakah record_path adalah list atau string
            if isinstance(record_path, str):
                df = pd.json_normalize(data[record_path], meta=meta, record_path=None, errors='ignore')
            elif isinstance(record_path, list): # jika record_path adalah list of keys
                df = pd.json_normalize(data, record_path=record_path, meta=meta, errors='ignore')
            else: # Jika struktur lain, mungkin perlu penyesuaian
                 df = pd.DataFrame(data) # Fallback jika record_path tidak sesuai
            print(f"Berhasil memuat dan menormalisasi bagian dari {filepath} ke DataFrame dengan {len(df)} baris.")
            return df
        else:
            print(f"Format data tidak didukung untuk konversi langsung ke DataFrame untuk {filepath} atau record_path tidak sesuai.")
            return pd.DataFrame() # Kembalikan DataFrame kosong
            
    except FileNotFoundError:
        print(f"ERROR: File {filepath} tidak ditemukan.")
        return pd.DataFrame()
    except json.JSONDecodeError as e:
        print(f"ERROR: File {filepath} bukan format JSON yang valid. Kesalahan: {e}")
        return pd.DataFrame()
    except Exception as e:
        print(f"ERROR saat memuat atau memproses {filepath}: {e}")
        return pd.DataFrame()

# --- 1. Load Data yang Sudah Digabung dan Data Mentah Lainnya ---
print("Memuat data...\n")
df_players_merged = load_json_to_df('players_merged_with_all.json') # Hasil dari skrip sebelumnya
df_coaches_enriched = load_json_to_df('coaches_merged_enriched.json') # Hasil dari skrip sebelumnya
df_teams_annual_raw = load_json_to_df('teams.json') # File asli teams.json
df_series_post_raw = load_json_to_df('series_post.json') # File asli series_post.json

# --- 2. Contoh Pre-processing untuk DataFrame Pemain (`df_players_merged`) ---
if not df_players_merged.empty:
    print("\n--- Pre-processing Pemain ---")

    # A. Explode Kolom Posisi (jika ada pemain dengan multiple posisi seperti "G-F")
    #    Asumsi 'pos' adalah string, bisa dipisah dengan '-'
    #    Ini akan membuat baris duplikat jika pemain punya >1 posisi
    # df_players_exploded_pos = df_players_merged.assign(pos=df_players_merged['pos'].str.split('-')).explode('pos')
    # print("\nContoh data pemain setelah explode posisi (5 baris pertama):")
    # print(df_players_exploded_pos[['playerID', 'firstName', 'lastName', 'pos']].head())
    # print(f"Jumlah baris setelah explode posisi: {len(df_players_exploded_pos)}")
    # print("Nilai unik posisi setelah explode:")
    # print(df_players_exploded_pos['pos'].value_counts())

    # Alternatif yang lebih baik untuk posisi: buat kolom boolean untuk setiap posisi dasar
    # atau normalisasi ke posisi utama. Untuk sekarang, kita biarkan apa adanya.

    # B. Cek Nilai Unik dan Range untuk Statistik Kunci di career_teams
    #    Kita perlu meng-explode career_teams dulu untuk analisis per musim
    if 'career_teams' in df_players_merged.columns:
        df_player_seasons = df_players_merged.explode('career_teams').reset_index(drop=True)
        # Gabungkan data musim ke kolom utama DataFrame
        df_player_seasons = pd.concat([
            df_player_seasons.drop(['career_teams'], axis=1), 
            df_player_seasons['career_teams'].apply(pd.Series)
        ], axis=1)
        
        print(f"\nDataFrame pemain per musim dibuat dengan {len(df_player_seasons)} baris.")

        if not df_player_seasons.empty:
            print("\nStatistik deskriptif untuk kolom numerik utama (per musim pemain):")
            numeric_cols_seasons = ['GP', 'points', 'rebounds', 'assists', 'minutes', 'year']
            existing_numeric_cols = [col for col in numeric_cols_seasons if col in df_player_seasons.columns and pd.api.types.is_numeric_dtype(df_player_seasons[col])]
            if existing_numeric_cols:
                 print(df_player_seasons[existing_numeric_cols].describe().round(2))
            
            print("\nRange tahun musim pemain:")
            if 'year' in df_player_seasons.columns and not df_player_seasons['year'].isnull().all():
                print(f"Min: {df_player_seasons['year'].min()}, Max: {df_player_seasons['year'].max()}")
            else:
                print("Kolom 'year' tidak ada atau kosong.")

            # C. Hitung Statistik per Game (PPG, APG, RPG)
            # Pastikan GP tidak nol untuk menghindari pembagian dengan nol
            df_player_seasons['PPG'] = (df_player_seasons['points'] / df_player_seasons['GP']).fillna(0).round(1)
            df_player_seasons['APG'] = (df_player_seasons['assists'] / df_player_seasons['GP']).fillna(0).round(1)
            df_player_seasons['RPG'] = (df_player_seasons['rebounds'] / df_player_seasons['GP']).fillna(0).round(1)
            
            print("\nContoh data pemain per musim dengan PPG, APG, RPG (5 baris pertama):")
            print(df_player_seasons[['playerID', 'year', 'tmID', 'GP', 'points', 'PPG', 'assists', 'APG', 'rebounds', 'RPG']].head())

            # D. Buat Label Dekade untuk Musim Pemain
            # Hapus baris dengan 'year' NaN sebelum konversi ke int
            df_player_seasons_cleaned = df_player_seasons.dropna(subset=['year'])
            df_player_seasons_cleaned['year'] = df_player_seasons_cleaned['year'].astype(int)
            
            df_player_seasons_cleaned['decade_label'] = ((df_player_seasons_cleaned['year'] // 10) * 10).astype(str) + "s"
            print("\nJumlah musim pemain per dekade:")
            print(df_player_seasons_cleaned['decade_label'].value_counts().sort_index())
            
            # E. Analisis Penghargaan Pemain
            if 'player_awards' in df_players_merged.columns:
                all_awards = []
                for awards_list in df_players_merged['player_awards']:
                    if isinstance(awards_list, list):
                        for award_info in awards_list:
                            if isinstance(award_info, dict) and 'award' in award_info:
                                all_awards.append(award_info['award'])
                if all_awards:
                    award_counts = Counter(all_awards)
                    print("\nJumlah penghargaan pemain (Top 10):")
                    for award, count in award_counts.most_common(10):
                        print(f"- {award}: {count}")
else:
    print("DataFrame pemain kosong, pre-processing pemain dilewati.")

# --- 3. Contoh Pre-processing untuk DataFrame Pelatih (`df_coaches_enriched`) ---
if not df_coaches_enriched.empty:
    print("\n--- Pre-processing Pelatih ---")
    
    # A. Explode stint kepelatihan (array 'teams' di dalam setiap dokumen pelatih)
    #    Setiap baris akan menjadi satu stint (musim-tim) untuk seorang pelatih
    if 'teams' in df_coaches_enriched.columns:
        df_coach_stints = df_coaches_enriched.explode('teams').reset_index(drop=True)
        # Gabungkan data stint ke kolom utama
        df_coach_stints = pd.concat([
            df_coach_stints.drop(['teams'], axis=1),
            df_coach_stints['teams'].apply(pd.Series)
        ], axis=1)
        print(f"\nDataFrame stint pelatih dibuat dengan {len(df_coach_stints)} baris.")

        if not df_coach_stints.empty:
            # B. Hitung Win Percentage untuk setiap stint
            df_coach_stints['total_games'] = df_coach_stints['won'] + df_coach_stints['lost']
            df_coach_stints['win_pct'] = (df_coach_stints['won'] / df_coach_stints['total_games'] * 100).fillna(0).round(1)
            
            print("\nContoh data stint pelatih dengan Win % (5 baris pertama):")
            print(df_coach_stints[['coachID', 'year', 'tmID', 'won', 'lost', 'win_pct', 'post_wins', 'post_losses']].head())

            # C. Analisis Penghargaan Pelatih
            if 'coach_awards' in df_coaches_enriched.columns: # Cek di df_coaches_enriched (sebelum explode)
                all_coach_awards_list = []
                for awards_list in df_coaches_enriched['coach_awards']:
                     if isinstance(awards_list, list):
                        for award_info in awards_list:
                            if isinstance(award_info, dict) and 'award' in award_info:
                                all_coach_awards_list.append(award_info['award'])
                if all_coach_awards_list:
                    coach_award_counts = Counter(all_coach_awards_list)
                    print("\nJumlah penghargaan pelatih:")
                    for award, count in coach_award_counts.most_common():
                        print(f"- {award}: {count}")
else:
    print("DataFrame pelatih kosong, pre-processing pelatih dilewati.")


# --- 4. Contoh Pre-processing untuk DataFrame Tim (`df_teams_annual_raw`) ---
if not df_teams_annual_raw.empty:
    print("\n--- Pre-processing Tim Tahunan (teams.json) ---")
    
    # A. Hitung Win Percentage
    df_teams_annual_raw['total_games_calc'] = df_teams_annual_raw['won'] + df_teams_annual_raw['lost']
    # Gunakan 'games' jika ada d