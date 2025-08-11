# app.py — 動態權重 + 必要條件篩選 + 六項推薦公式（完整可用版）
from flask import Flask, request, jsonify
from dotenv import load_dotenv
import os, json, time
from typing import Dict, Any, Optional, Tuple, List

# －－ 讀取 .env（固定抓和 app.py 同資料夾） －－
ENV_PATH = os.path.join(os.path.dirname(__file__), ".env")
load_dotenv(dotenv_path=ENV_PATH)

OPENAI_API_KEY = os.getenv("OPENAI_API_KEY", "").strip()
if not OPENAI_API_KEY:
    raise RuntimeError(f"缺少 OPENAI_API_KEY，請確認 {ENV_PATH} 內已設定並存檔")
os.environ["OPENAI_API_KEY"] = OPENAI_API_KEY  # 讓下游套件可讀到

# －－ 向量／LLM（沿用你原本環境的匯入方式；之後要消警告再換新版匯入） －－
from langchain.embeddings import HuggingFaceEmbeddings
from langchain.vectorstores import Chroma
from langchain.chat_models import ChatOpenAI

app = Flask(__name__)

# －－ Vector DB －－
embedding = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
db = Chroma(persist_directory="./chroma_db", embedding_function=embedding)

# －－ LLM（低溫讓輸出穩定） －－
llm = ChatOpenAI(model_name="gpt-3.5-turbo", temperature=0.2)

SCORING_KEYS = ["Quiet", "Tasty", "Cheap", "Seat", "Wifi", "Music"]

# =========================
# Few-shot（你可以持續增補）
# =========================
FEW_SHOT: List[Dict[str, Any]] = [
  {
    "user": "想找台北大安區 安靜 有插座 適合久坐",
    "required": {"City":"台北", "Socket":"有插座", "Limited_time":"不限時",
                 "Name":None, "Address":None, "Open_time":None, "Standing_desk":None},
    "weights":  {"Quiet":0.35,"Seat":0.25,"Wifi":0.15,"Tasty":0.10,"Cheap":0.10,"Music":0.05},
    "rationale":"重視安靜與久坐，Quiet/Seat 高；需穩定網路，Wifi 次高。"
  },
  {
    "user": "想聊天聚會、甜點好吃，地點不限",
    "required": {"City":None,"Socket":None,"Limited_time":None,"Name":None,"Address":None,"Open_time":None,"Standing_desk":None},
    "weights":  {"Tasty":0.35,"Seat":0.20,"Music":0.15,"Cheap":0.15,"Wifi":0.10,"Quiet":0.05},
    "rationale":"以口味與氛圍為主，Tasty 高、Music 稍高；Quiet 低。"
  },
  {
    "user": "要辦公用、網路穩、最好不限時",
    "required": {"Limited_time":"不限時","City":None,"Socket":None,"Name":None,"Address":None,"Open_time":None,"Standing_desk":None},
    "weights":  {"Wifi":0.30,"Quiet":0.25,"Seat":0.20,"Tasty":0.10,"Cheap":0.10,"Music":0.05},
    "rationale":"工作情境看中 Wifi/Quiet/Seat。"
  },
  {
    "user": "要便宜、能坐久、地點新北都可以",
    "required": {"City":"新北","Limited_time":"不限時","Name":None,"Address":None,"Open_time":None,"Socket":None,"Standing_desk":None},
    "weights":  {"Cheap":0.40,"Seat":0.20,"Wifi":0.15,"Tasty":0.15,"Quiet":0.05,"Music":0.05},
    "rationale":"以價格為先，久坐次之。"
  },
  {
    "user": "想安靜讀書，插座必須要有",
    "required": {"Socket":"有插座","Name":None,"City":None,"Address":None,"Open_time":None,"Limited_time":None,"Standing_desk":None},
    "weights":  {"Quiet":0.30,"Seat":0.25,"Wifi":0.20,"Tasty":0.10,"Cheap":0.10,"Music":0.05},
    "rationale":"讀書：Quiet/Seat/Wifi 重要。"
  },
  {
    "user": "不限城市，氣氛放鬆、不要太吵，甜點也要好吃",
    "required": {"City":None,"Name":None,"Address":None,"Open_time":None,"Limited_time":None,"Socket":None,"Standing_desk":None},
    "weights":  {"Tasty":0.30,"Quiet":0.25,"Seat":0.20,"Music":0.15,"Wifi":0.05,"Cheap":0.05},
    "rationale":"甜點與安靜兼顧，氛圍(Music)略高。"
  }
]

def examples_block() -> str:
    """把 FEW_SHOT 變成提示字串給 GPT 學習"""
    lines = []
    for ex in FEW_SHOT:
        lines.append("使用者需求：" + ex["user"])
        lines.append("示例輸出：")
        lines.append(json.dumps({
            "required": ex["required"],
            "weights": ex["weights"],
            "rationale": ex["rationale"]
        }, ensure_ascii=False))
        lines.append("")
    return "\n".join(lines)

# ==========================================
# 工具：標準化、計分、必要條件比對、權重產生
# ==========================================
def normalize_1_to_5(x: Optional[float]) -> float:
    try:
        v = float(x)
    except (TypeError, ValueError):
        return 0.0
    if v < 1: v = 1
    if v > 5: v = 5
    return v / 5.0

def compute_score(meta: Dict[str, Any], weights: Dict[str, float]) -> Dict[str, Any]:
    """依「本次權重」計分，回傳完整明細"""
    norm = {k: normalize_1_to_5(meta.get(k)) for k in SCORING_KEYS}
    weighted = {k: norm[k] * float(weights.get(k, 0.0)) for k in SCORING_KEYS}
    score = sum(weighted.values())
    return {
        "metrics":   {k: meta.get(k, None) for k in SCORING_KEYS},
        "normalized":norm,
        "weights":   {k: float(weights.get(k, 0.0)) for k in SCORING_KEYS},
        "weighted":  {k: round(weighted[k], 6) for k in SCORING_KEYS},
        "Score":     round(score, 6),
    }

def metadata_match(meta: Dict[str, Any], req: Dict[str, Optional[str]]) -> bool:
    """七項必要條件精準比對：四項字串『包含』；三項枚舉（中文值）『完全相等』"""
    def contains(a: str, b: str) -> bool:
        return (a or "").strip() and (b or "").strip() and (b.strip() in a.strip())

    for k in ["Name", "Address", "City", "Open_time"]:
        if req.get(k):
            if not contains(str(meta.get(k, "")), str(req[k])):
                return False

    for k in ["Limited_time", "Socket", "Standing_desk"]:
        if req.get(k):
            if str(meta.get(k, "")).strip() != str(req[k]).strip():
                return False
    return True

def parse_require_and_weights(user_question: str) -> Tuple[Dict[str, Optional[str]], Dict[str, float], str]:
    """同時產生：七項必要條件 + 本次權重(總和=1) + 理由"""
    schema_hint = """
只輸出一段 JSON，格式如下（鍵名與大小寫不可更動）：
{
  "required": {
    "Name": null | string,
    "City": null | string,
    "Address": null | string,
    "Open_time": null | string,
    "Limited_time": null | "不限時" | "限時" | "視情況而定",
    "Socket": null | "有插座" | "無插座" | "部分座位有插座",
    "Standing_desk": null | "有站立座位" | "無站立座位" | "可能有站立座位"
  },
  "weights": {
    "Quiet": number,
    "Tasty": number,
    "Cheap": number,
    "Seat": number,
    "Wifi": number,
    "Music": number
  },
  "rationale": string
}
要求：
- weights 六個係數皆 >= 0，且總和=1（小數點三位以內）
- 若需求未提到某因子，仍要分配，但可給較低權重
- 僅輸出 JSON，不要多餘文字
""".strip()

    prompt = f"""你是咖啡廳推薦系統的權重規劃員。請根據使用者需求，
同時輸出七個必要條件（無明確描述則為 null）與本次評分權重（六項、總和=1）。

以下是範例（務必學習它們的映射關係）：
{examples_block()}

{schema_hint}

使用者需求：{user_question}
"""
    resp = llm.predict(prompt)

    try:
        obj = json.loads(resp)
    except Exception:
        obj = {
            "required": {k: None for k in ["Name","City","Address","Open_time","Limited_time","Socket","Standing_desk"]},
            "weights":  {"Quiet":0.20,"Tasty":0.20,"Cheap":0.20,"Seat":0.20,"Wifi":0.10,"Music":0.10},
            "rationale":"回退：JSON 解析失敗，使用預設權重。"
        }

    required = obj.get("required", {})
    weights  = obj.get("weights", {})
    rationale = obj.get("rationale", "")

    # 補鍵 + 數值化
    for k in ["Name","City","Address","Open_time","Limited_time","Socket","Standing_desk"]:
        required.setdefault(k, None)
    for k in SCORING_KEYS:
        try:
            weights[k] = float(weights.get(k, 0))
        except Exception:
            weights[k] = 0.0

    # 非負 + 正規化到 1
    s = sum(max(0.0, v) for v in weights.values())
    if s <= 0:
        weights = {"Quiet":0.20,"Tasty":0.20,"Cheap":0.20,"Seat":0.20,"Wifi":0.10,"Music":0.10}
        rationale = (rationale + "（回退：權重總和<=0，改用預設）").strip()
    else:
        weights = {k: max(0.0, v)/s for k, v in weights.items()}

    # 三位小數 + 校正總和=1
    weights = {k: round(v, 3) for k, v in weights.items()}
    diff = round(1.0 - sum(weights.values()), 3)
    if abs(diff) >= 0.001:
        kmax = max(weights, key=lambda k: weights[k])
        weights[kmax] = round(weights[kmax] + diff, 3)

    return required, weights, rationale

# （可選）把每次的權重決策記錄下來，方便日後擴充 Few-shot
def log_weights(user_question: str, required: Dict[str, Any], weights: Dict[str, float], rationale: str):
    try:
        with open("weights_log.jsonl", "a", encoding="utf-8") as f:
            f.write(json.dumps({
                "ts": time.strftime("%Y-%m-%d %H:%M:%S"),
                "question": user_question,
                "required": required,
                "weights": weights,
                "rationale": rationale
            }, ensure_ascii=False) + "\n")
    except Exception:
        pass

def format_formula_string(weights: Dict[str, float]) -> str:
    def pct(x): return f"{round(100*x)}%"
    return (
        f"Score = {weights['Quiet']:.3f}·Quiet' + {weights['Tasty']:.3f}·Tasty' + "
        f"{weights['Cheap']:.3f}·Cheap' + {weights['Seat']:.3f}·Seat' + "
        f"{weights['Wifi']:.3f}·Wifi' + {weights['Music']:.3f}·Music'\n"
        f"（權重：Quiet {pct(weights['Quiet'])}, Tasty {pct(weights['Tasty'])}, "
        f"Cheap {pct(weights['Cheap'])}, Seat {pct(weights['Seat'])}, "
        f"Wifi {pct(weights['Wifi'])}, Music {pct(weights['Music'])}）"
    )

# ==================
# API
# ==================
@app.route("/ask", methods=["POST"])
def ask():
    data = request.get_json(force=True) or {}
    user_question = (data.get("question") or "").strip()
    debug = bool(data.get("debug", True))  # 預設顯示明細

    if not user_question:
        return jsonify({"error": "Missing question"}), 400

    # A) GPT 產生：必要條件 + 本次權重
    required, weights, rationale = parse_require_and_weights(user_question)
    log_weights(user_question, required, weights, rationale)

    # B) 先語意檢索，再依必要條件篩選
    all_docs = db.similarity_search(user_question, k=50)
    strict = [doc for doc in all_docs if metadata_match(doc.metadata, required)]
    if len(strict) >= 5:
        candidates, partial = strict[:10], False
    else:
        need = 10 - len(strict)
        rest = [d for d in all_docs if d not in strict][:need]
        candidates, partial = strict + rest, True

    if not candidates:
        return jsonify({
            "required": required, "weights": weights,
            "answer": "很抱歉，找不到合適的候選資料。"
        })

    # C) 用「本次權重」計分
    scored: List[Dict[str, Any]] = []
    for doc in candidates:
        meta = doc.metadata
        detail = compute_score(meta, weights)
        scored.append({
            "Name": meta.get("Name", "未知名稱"),
            "City": meta.get("City"),
            "Address": meta.get("Address"),
            "Open_time": meta.get("Open_time"),
            "Limited_time": meta.get("Limited_time"),
            "Socket": meta.get("Socket"),
            "Standing_desk": meta.get("Standing_desk"),
            "Mrt": meta.get("Mrt"),
            "Url": meta.get("Url"),
            "score_detail": detail,
        })

    scored.sort(key=lambda x: x["score_detail"]["Score"], reverse=True)
    top3 = scored[:3]

    # D) 組合回覆：自然口語 + 公式 + 理由 +（可選）各店明細
    header = ("⚠️ 找不到完全符合所有必要條件的 5 間；以下為盡量符合的推薦：\n"
              if partial else
              "✅ 根據您的需求（已解析必要條件）之推薦：\n")
    header += "\n【本次推薦公式】\n" + format_formula_string(weights) + "\n"
    if rationale:
        header += f"【權重理由】{rationale}\n"

    blocks = []
    for i, row in enumerate(top3, 1):
        d = row["score_detail"]
        reasons = []
        for k in ["Quiet","Seat","Wifi","Tasty","Cheap"]:
            if d["normalized"][k] >= 0.8:
                reasons.append(f"{k}表現佳")
        reason_text = "、".join(reasons) if reasons else "整體表現均衡"

        block = [
            f"第 {i} 名：{row['Name']}（總分 {d['Score']:.3f}）",
            f"地點：{row.get('City','')} | {row.get('Address','')}",
            f"營業：{row.get('Open_time','')} | 限時：{row.get('Limited_time','')} | 插座：{row.get('Socket','')} | 站立：{row.get('Standing_desk','')}",
            f"推薦理由：{reason_text}"
        ]
        if debug:
            block.append("")
            block.append("加權分數明細：")
            block.append("Factor | Raw | Normalized | Weight | Weighted")
            for k in SCORING_KEYS:
                raw = d["metrics"].get(k, None)
                norm = d["normalized"][k]
                w = d["weights"][k]
                ww = d["weighted"][k]
                block.append(f"{k} | {raw} | {norm:.3f} | {w:.3f} | {ww:.3f}")
            block.append(f"Score：{d['Score']:.3f}")
        blocks.append("\n".join(block))

    answer = header + "\n\n".join(blocks)
    return jsonify({"required": required, "weights": weights, "top3": top3, "answer": answer})

if __name__ == "__main__":
    app.run(debug=True)
