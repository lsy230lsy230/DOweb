import os
import time
import shutil
import re

src_web = r"C:\dancescore\Web"
dst_web = r"Y:\results"
src_recall_file = r"C:\dancescore\Recall\index.php"
dst_recall_file = r"Y:\Recall\index.php"
CHECK_INTERVAL = 2

def replace_content(content):
    # 1. Tag를 등번호로 치환
    content = re.sub(r"\btag\b", "등번호", content, flags=re.IGNORECASE)
    # 2. 텍스트 테이블 헤더에 등위 추가 (Recall index.php가 텍스트 테이블인 경우)
    content = re.sub(r"^(등번호\s+\S+\s+\S+)", r"등위\t\1", content, flags=re.MULTILINE)
    # 3. HTML 테이블 헤더에 등위 추가 (Recall index.php가 HTML 테이블인 경우)
    content = re.sub(r"(<th[^>]*?>\s*)</th>", r"\1등위</th>", content, count=1, flags=re.IGNORECASE)
    # 4. 경기 진출 치환
    content = re.sub(r"Recalled\s+(\d+)\s+Couples\s+to\s+Item\s+(\d+)", r"\1커플이 \2번 이벤트로 진출합니다.", content)
    content = re.sub(r"Recalled\s+(\d+)\s+Couples\s+to\s+(\d+)", r"\1커플이 \2번 이벤트로 진출합니다.", content)
    # 5. 기타 라벨 치환
    content = re.sub(r"Adjudicators", "심사위원", content)
    content = re.sub(r"Marks", "득점", content)
    content = re.sub(r"Description", "부문", content)
    # 6. Results Copyright of 삭제
    content = re.sub(r"Results Copyright of", "", content, flags=re.IGNORECASE)
    # 7. DanceSportLive 문구/링크 삭제
    content = re.sub(r'<a[^>]*?>\s*DanceSportLive[^<]*</a>', '', content, flags=re.IGNORECASE)
    content = re.sub(r'DanceSportLive', '', content, flags=re.IGNORECASE)
    # 8. 불필요한 문구/링크/저작권 제거
    content = re.sub(r'<a[^>]*?>\s*DanceScore Scrutineering Software\s*</a>', '', content, flags=re.IGNORECASE)
    content = re.sub(r'DanceScore Scrutineering Software', '', content, flags=re.IGNORECASE)
    content = re.sub(r'<a[^>]*?>\s*DanceScore\s*</a>', '', content, flags=re.IGNORECASE)
    content = re.sub(r'<a[^>]*?>\s*DanceSportLive\.net\s*</a>', '', content, flags=re.IGNORECASE)
    content = re.sub(r'©\s*DanceScore\s*-\s*DanceSportLive\.net\s*V7-5-00', '', content, flags=re.IGNORECASE)
    content = re.sub(r'DanceScore\s*-\s*DanceSportLive\.net\s*V7-5-00', '', content, flags=re.IGNORECASE)
    content = re.sub(r'DanceScoreLive\.net', '', content, flags=re.IGNORECASE)
    # 9. "© - V7-5-00" 삭제
    content = re.sub(r'©\s*-\s*V7-5-00', '', content, flags=re.IGNORECASE)
    # 10. Home 링크 변경
    content = re.sub(r"<a\s+href=[\'\"]?(\.\./)?index\.html[\'\"]?\s*>Home\s*</a>",
                     r'<a href="https://www.danceoffice.net/results/">Home</a>', content, flags=re.IGNORECASE)
    content = re.sub(r"<a\s+href=[\'\"]?(\.\./)?index\.php[\'\"]?\s*>Home\s*</a>",
                     r'<a href="https://www.danceoffice.net/results/">Home</a>', content, flags=re.IGNORECASE)
    content = re.sub(r"<a\s+href=[\'\"]?.*?home.*?[\'\"]?\s*>Home\s*</a>",
                     r'<a href="https://www.danceoffice.net/results/">Home</a>', content, flags=re.IGNORECASE)
    return content

def convert_and_copy(src_file, dst_file):
    try:
        with open(src_file, "rb") as f:
            raw = f.read()
        # 인코딩 자동 감지 및 변환
        if raw.startswith(b'\xff\xfe'):
            text = raw.decode('utf-16le', errors='ignore')
        elif raw.startswith(b'\xfe\xff'):
            text = raw.decode('utf-16be', errors='ignore')
        elif raw.startswith(b'\xef\xbb\xbf'):
            text = raw.decode('utf-8-sig', errors='ignore')
        else:
            try:
                text = raw.decode('utf-8')
            except:
                try:
                    text = raw.decode('euc-kr')
                except:
                    text = raw.decode('cp949', errors='ignore')
        new_content = replace_content(text)
        ensure_dir_exists(dst_file)
        with open(dst_file, "w", encoding="utf-8") as f:
            f.write(new_content)
        shutil.copystat(src_file, dst_file)  # mtime 맞춤
        print(f"[치환/복사] {src_file} → {dst_file}")
    except Exception as e:
        print(f"[치환/복사 실패] {src_file} → {dst_file}: {e}")

def copy_file_with_rules(src_file, dst_file, rel_dir, fname):
    ext = os.path.splitext(fname)[1].lower()
    # 임시 파일(.tmp)은 건너뜀
    if fname.lower().endswith('.tmp'):
        print(f"[건너뜀] {src_file} (임시 파일)")
        return
    # results index.php는 건너뜀(유지)
    if rel_dir == '.' and fname == "index.php":
        print(f"[건너뜀] {src_file} (index.php는 유지)")
        return
    # 텍스트 파일은 치환 복사
    if ext in [".html", ".htm", ".txt", ".php"]:
        convert_and_copy(src_file, dst_file)
    else:
        ensure_dir_exists(dst_file)
        try:
            shutil.copy2(src_file, dst_file)
            shutil.copystat(src_file, dst_file)  # mtime 맞춤
            print(f"[일반복사] {src_file} → {dst_file}")
        except Exception as e:
            print(f"[복사 실패] {src_file} → {dst_file}: {e}")

def ensure_dir_exists(path):
    dirname = os.path.dirname(path)
    if dirname and not os.path.exists(dirname):
        os.makedirs(dirname, exist_ok=True)

def scan_files(base_dir):
    file_map = {}
    for root, dirs, files in os.walk(base_dir):
        for fname in files:
            # 임시 파일(.tmp)은 제외
            if fname.lower().endswith('.tmp'):
                continue
            fpath = os.path.join(root, fname)
            try:
                mtime = os.path.getmtime(fpath)
                rel_dir = os.path.relpath(root, base_dir)
                rel_path = os.path.join(rel_dir, fname) if rel_dir != "." else fname
                file_map[rel_path] = (mtime, os.path.getsize(fpath))
            except Exception:
                pass
    return file_map

def sync_web_folder(prev_web_files):
    curr_web_files = scan_files(src_web)
    for rel_path, (mtime, size) in curr_web_files.items():
        src_file = os.path.join(src_web, rel_path)
        dst_file = os.path.join(dst_web, rel_path)
        rel_dir = os.path.dirname(rel_path)
        fname = os.path.basename(rel_path)
        # 새 파일/변경 파일만 복사
        if (rel_path not in prev_web_files or
            prev_web_files[rel_path] != (mtime, size)):
            copy_file_with_rules(src_file, dst_file, rel_dir, fname)
    return curr_web_files

def sync_recall_index(prev_recall_mtime):
    # Recall index.php는 항상 치환 복사
    if os.path.exists(src_recall_file):
        recall_mtime = os.path.getmtime(src_recall_file)
        if prev_recall_mtime != recall_mtime:
            convert_and_copy(src_recall_file, dst_recall_file)
        return recall_mtime
    else:
        print(f"[Recall index.php 없음] {src_recall_file}")
        return prev_recall_mtime

def main():
    print("실시간 Web/Recall 폴더 감시 + 트리유지 복사 시작...")
    prev_web_files = scan_files(src_web)
    prev_recall_mtime = None
    while True:
        prev_web_files = sync_web_folder(prev_web_files)
        prev_recall_mtime = sync_recall_index(prev_recall_mtime)
        time.sleep(CHECK_INTERVAL)

if __name__ == "__main__":
    main()