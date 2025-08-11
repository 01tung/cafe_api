# build_rag.py (revised)
import json
from langchain_community.vectorstores import Chroma
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain.schema import Document
import os
import shutil

# === 中文轉換詞典（固定集合） ===
TRANSLATE = {
    "yes": {"Limited_time": "限時", "Socket": "有插座", "Standing_desk": "有站立座位"},
    "no": {"Limited_time": "不限時", "Socket": "無插座", "Standing_desk": "無站立座位"},
    "maybe": {"Limited_time": "視情況而定", "Socket": "部分座位有插座", "Standing_desk": "可能有站立座位"},
}

REQ_FIELDS = ["Name", "City", "Address", "Open_time", "Limited_time", "Socket", "Standing_desk"]
SCORE_FIELDS = ["Quiet", "Tasty", "Cheap", "Seat", "Wifi", "Music"]
EXTRA_FIELDS = ["Mrt", "Url", "ID"]  # 方便回傳

def load_data(path: str):
    with open(path, "r", encoding="utf-8") as f:
        raw = json.load(f)
    # 支援兩種常見結構：
    # 1) dict: {"data": [...]}
    # 2) list: [..., {"data": [...]}, ...]
    if isinstance(raw, dict) and "data" in raw:
        return raw["data"]
    if isinstance(raw, list):
        for obj in raw:
            if isinstance(obj, dict) and "data" in obj:
                return obj["data"]
    raise ValueError("無法在 JSON 中找到 'data' 清單，請確認檔案結構。")

def safe_str(item, key):
    val = item.get(key, "")
    return str(val).strip() if val not in (None, "") else "無資料"

def raw_yes_no_maybe(item, key):
    val = str(item.get(key, "")).strip().lower()
    return val if val in ("yes", "no", "maybe") else ""

def zh_value(raw_val, field):
    return TRANSLATE.get(raw_val, {}).get(field, "無資料")

def safe_float(item, key):
    val = item.get(key, None)
    try:
        if val in (None, ""):
            return None
        return float(val)
    except (TypeError, ValueError):
        return None

def make_page_content(meta):
    # 用中文描述，便於檢索
    return f"""咖啡廳名稱：{meta['Name']}
城市：{meta['City']}
地址：{meta['Address']}
營業時間：{meta['Open_time']}
限時與否：{meta['Limited_time']}
插座資訊：{meta['Socket']}
站立座位：{meta['Standing_desk']}
安靜程度：{meta.get('Quiet','無資料')}
美味程度：{meta.get('Tasty','無資料')}
價格便宜程度：{meta.get('Cheap','無資料')}
座位多寡：{meta.get('Seat','無資料')}
WiFi 穩定度：{meta.get('Wifi','無資料')}
背景音樂：{meta.get('Music','無資料')}
鄰近捷運站：{meta.get('Mrt','無資料')}
網址：{meta.get('Url','無資料')}
""".strip()

def main():
    data = load_data("cafes.json")
    documents = []
    skipped = 0

    for i, item in enumerate(data, start=1):
        name = safe_str(item, "Name")
        if name == "無資料":
            print(f"⚠️ 跳過第 {i} 筆資料：沒有名稱")
            skipped += 1
            continue

        # ----- 必要欄位（含 raw 與 zh） -----
        lt_raw = raw_yes_no_maybe(item, "Limited_time")
        so_raw = raw_yes_no_maybe(item, "Socket")
        sd_raw = raw_yes_no_maybe(item, "Standing_desk")

        meta = {
            # 基本識別
            "ID": safe_str(item, "ID") if item.get("ID") not in (None, "") else f"item_{i}",
            "Name": name,
            "City": safe_str(item, "City"),
            "Address": safe_str(item, "Address"),
            "Open_time": safe_str(item, "Open_time"),

            # 三個布林欄位（中文＆原始）
            "Limited_time_raw": lt_raw,
            "Socket_raw": so_raw,
            "Standing_desk_raw": sd_raw,
            "Limited_time": zh_value(lt_raw, "Limited_time"),
            "Socket": zh_value(so_raw, "Socket"),
            "Standing_desk": zh_value(sd_raw, "Standing_desk"),

            # 其餘回傳欄位
            "Mrt": safe_str(item, "Mrt"),
            "Url": safe_str(item, "Url"),
        }

        # ----- 6 個打分欄位（float 存進 metadata） -----
        for k in SCORE_FIELDS:
            meta[k] = safe_float(item, k)

        page_content = make_page_content(meta)
        documents.append(Document(page_content=page_content, metadata=meta))
        print(f"✅ 第 {i} 筆 | {name}")

    # 重新建立向量庫
    if os.path.exists("chroma_db"):
        shutil.rmtree("chroma_db")

    embedding = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
    vectorstore = Chroma.from_documents(documents, embedding=embedding, persist_directory="chroma_db")
    vectorstore.persist()

    print(f"\n✅ 向量資料庫建立完成，共 {len(documents)} 筆；跳過 {skipped} 筆")

if __name__ == "__main__":
    main()


