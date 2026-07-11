#!/usr/bin/env bash
# Package script — empacota o plugin como ZIP oficial para distribuicao via GitHub Releases.
# Estrutura esperada:
#   plugin-slug/
#     plugin-slug.php
#     readme.txt
#     uninstall.php
#     index.php
#     includes/
#     assets/
#     languages/
#     scripts/
#
# Uso:
#   bash scripts/package.sh              # usa versao do header
#   bash scripts/package.sh 1.2.3        # override

set -euo pipefail

SLUG="ml-optmize-pro"
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-}"

if [[ -z "$VERSION" ]]; then
    VERSION=$(grep -E '^\s*\*\s*Version:' "$ROOT_DIR/$SLUG.php" | head -1 | sed -E 's/.*Version:\s*([0-9.]+).*/\1/' || echo "")
fi

if [[ -z "$VERSION" ]]; then
    echo "Erro: nao foi possivel detectar a versao. Forneca como argumento: $0 1.2.3" >&2
    exit 1
fi

OUT_NAME="${SLUG}-v${VERSION}.zip"
OUT_PATH="${ROOT_DIR}/${OUT_NAME}"

if [[ -f "$OUT_PATH" ]]; then
    rm -f "$OUT_PATH"
fi

# Pre-flight checks: estrutura minima
echo "[$SLUG] Verificando estrutura do source..."
for f in "$SLUG.php" "readme.txt" "uninstall.php" "index.php" "includes" "assets" "languages"; do
    if [[ ! -e "$ROOT_DIR/$SLUG/$f" ]]; then
        echo "Erro: arquivo obrigatorio ausente: $SLUG/$f" >&2
        exit 2
    fi
done
echo "[$SLUG] OK."

# Empacota com cd na pasta do plugin para que a primeira entrada seja "$SLUG/"
cd "$ROOT_DIR/$SLUG"

# Usar zip com -X (no extra attrs) e -r.
zip -r -X "$OUT_PATH" . -x "*.DS_Store" "*/node_modules/*" "*/.git/*" "*/__MACOSX/*" "*/.vscode/*" "*/.idea/*"

echo "[$SLUG] ZIP criado: $OUT_PATH"
echo "[$SLUG] Tamanho: $(du -h "$OUT_PATH" | cut -f1)"

# Validacao pos-build
echo "[$SLUG] Validando estrutura do ZIP (unzip -l)..."
unzip -l "$OUT_PATH" | head -30
FIRST=$(unzip -l "$OUT_PATH" | awk 'NR==4{print $4}')
if [[ "$FIRST" != "$SLUG/"* ]]; then
    echo "ERRO: primeira entrada do ZIP nao eh '$SLUG/' (foi '$FIRST'). Estrutura invalida." >&2
    exit 3
fi
if unzip -l "$OUT_PATH" | grep -q "__MACOSX"; then
    echo "ERRO: ZIP contem __MACOSX/. Reempacote sem isso." >&2
    exit 4
fi
echo "[$SLUG] Validacao OK. Asset filename esperado: $OUT_NAME"
