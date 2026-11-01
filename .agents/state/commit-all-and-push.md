# Commit domain extractions and push
Objective: user explicitly requested commits for ALL pending changes in C:/Herd/signals-rental-framework, push, then next easiest domain recommendation.
Baseline: main 458596a4; fetched origin/main matches baseline (0 ahead/0 behind). Index initially empty. Dense nested repository clean; no Dense changes to commit.
Inventory: 329 pending source/state files across Activities, Stocktakes, Currency, required shared integration, one formatting-only Crew query change, domain-aware Ransack test. No dependency or historical migration changes.
Verification: prior extraction suites recorded in individual domain checkpoints. Delivery rerun 23 cross-domain compatibility/architecture/presentation tests passed (68 assertions). Dirty Pint plus whole Activities/Stocktakes/Currency Pint passed. High-confidence secrets scan237 existing changed/new files found0 candidate files. Whitespace checks passed (existing SignalsClearDemo line-ending warning only).
Commit plan: Crew formatting; Activities native domain+integration; Stocktakes native domain+integration; Currency domain+integration+Ransack scanner; delivery records. Shared files staged per domain through index-only intermediate versions; working files remain untouched.
Next: finish source commits, stage/review remaining checkpoints, ordinary push origin main; verify local/remote HEAD equality and clean root/Dense status. Never force/rewrite.
Recommendation read-only audit: Lists/ListValues next (2models6actions6DTOs,policy,2factories,seeder,2APIcontrollers,4nativePresentation conversions;6web+18APIroutes including legacy aliases). Keep Countries/Tax/CustomFields outside boundary.

Source commits completed (staged diffs reviewed before each commit):
1bc9f7cb Extract Currency domain and preserve exchange-rate integration
229d0242 Extract Stocktakes domain with native Livewire presentation
b7e9c128 Extract Activities domain with native Livewire presentation
8e5cda2f Format Crew availability window output
Only delivery checkpoints remain; source working tree matches reviewed index. Next: commit these records, ordinary push, verify clean working tree and remote/local HEAD match.
