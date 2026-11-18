# Git

## Branches

- `main` : version stable. On n'y travaille jamais directement.
- `dev` : branche d'intégration. Tout le travail y est fusionné.
- Pour travailler sur un module, créez une branche depuis `dev`, nommée d'après le fichier du module dans `docs/fonctionnel/modules/` (par exemple `module/06-vote`, `module/02-referentiel-des-structures`).
- Quand le travail du module est terminé, que les tests passent et que Pint est appliqué, fusionnez la branche dans `dev` :

```bash
git switch dev
git merge --no-ff module/06-vote
```

- Une modification qui ne relève d'aucun module (configuration, charte, documentation générale) se fait sur `dev`.

## Messages de commit

- Une seule ligne courte, en français, à l'impératif ou au présent : « Ajoute le bulletin de vote », « Corrige le contraste du badge ». Pas de corps de message détaillé, pas de liste.
- N'utilisez jamais de tiret cadratin (—) ni de tiret demi-cadratin (–) dans un message de commit.
- N'ajoutez aucune ligne de co-auteur (`Co-Authored-By`) ni aucune autre mention en pied de message : le message tient sur sa seule ligne.
