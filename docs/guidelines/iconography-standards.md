# Iconography & Semantic Visual Standards (Anti-AI Slop)

## 1. Core Rule: Zero Sparkles Policy
**Never use `SparklesIcon` (or `Sparkles`, ✨, magic wand symbols) across any UI component or view.**
The Sparkles icon is generic AI slop that degrades executive credibility and obscures the technical reality of the platform. Foresight Radar is an executive intelligence and strategic foresight system, not a consumer gimmick.

---

## 2. Designated Semantic Replacements
Always use explicit, domain-accurate icons from `lucide-react`:

| Context / Purpose | Prohibited Icon | Mandatory Alternative | Rationale |
| :--- | :--- | :--- | :--- |
| **AI / Machine Model / Engine Badge** | `SparklesIcon` | `CpuIcon` | Represents computational intelligence and processor/model engine. |
| **Autonomous Action / LLM Generation** | `SparklesIcon` | `BotIcon` | Represents synthetic assistant synthesis / generation. |
| **AI Settings / Model Configuration** | `SparklesIcon` | `SlidersHorizontalIcon` | Represents parameter tuning and provider configuration. |
| **Real-time Pipeline / Ingestion Action**| `SparklesIcon` | `ZapIcon` | Represents active real-time ingest/pipeline processing. |
| **Resilience & Security Metrics** | `SparklesIcon` | `ShieldCheckIcon` | Semantically denotes robust governance and systemic defense. |
| **Growth & Market Trajectory** | `SparklesIcon` | `TrendingUpIcon` | Semantically denotes upward momentum and economic expansion. |
| **Failure / Error Alert Status** | `SparklesIcon` | `AlertCircleIcon` | Semantically conveys operational disruption or validation error. |

---

## 3. Enforcement & Verification
- Any PR or commit introducing `Sparkles` or `SparklesIcon` into `resources/js/` fails quality review.
- Always verify with `git grep Sparkles resources/js` prior to completing any UI task.

