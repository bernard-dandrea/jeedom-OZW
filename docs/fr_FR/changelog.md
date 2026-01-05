# Changelog plugin OZW

# 05/01/2026

- Remplacement event par checkAndUpdateCmd pour eviter répétition des valeurs dans history
- Déplacement de la documentation dans un repository github séparé afin de pouvoir mettre à jour la documentation sans générer un update du plugin

# 07/11/2024

- Passage des methodes cron en static pour éviter erreur en PHP 8
- ID devient Référence WEB dans les questions
  
# 25/02/2024

- Mise à jour documentation

# 22/01/2024

- Ajout d'une commande Refresh
- Longueur maxi des noms de champs portée à 100
- Pour les champs info de type Numeric, le widget est Line (et non Defaut)

# 13/12/2023

- Mise à jour documentation (suppression FAQ)

# 27/09/2023

- Lors de la création du device, le nom est égal au type suivi du numéro de série (précédemment, le name était repris mais il pouvait être vide)

# 29/05/2023

- Initial load
