#!/usr/bin/env sh
# Valida a sintaxe de todos os arquivos PHP e JS e roda o teste do leitor do planejamento.
# Uso (na raiz do projeto): sh tests/validar_sintaxe.sh
cd "$(dirname "$0")/.." || exit 1
erro=0

echo "== PHP: sintaxe =="
for f in $(find app public scripts tests -name '*.php' | sort); do
  if php -l "$f" > /dev/null 2>&1; then echo "OK    $f"; else echo "ERRO  $f"; php -l "$f"; erro=1; fi
done

echo; echo "== JavaScript: sintaxe =="
if command -v node > /dev/null 2>&1; then
  for f in public/js/*.js; do
    if node --check "$f" 2> /dev/null; then echo "OK    $f"; else echo "ERRO  $f"; erro=1; fi
  done
else
  echo "(node não encontrado; etapa ignorada)"
fi

echo; echo "== Teste do leitor do planejamento =="
php tests/testar_planejamentos.php || erro=1

if command -v node > /dev/null 2>&1; then
  echo; echo "== Teste do interpretador de PDF =="
  node tests/testar_pdf.js || erro=1
fi

exit $erro
