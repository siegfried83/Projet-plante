/* ============================================
   SCRIPT RECETTES PAGE - Gestion des recettes
   ============================================ */

// Variables globales pour stocker la recette et la plante générées
let currentAiRecipe = null;
let currentPlant = null;

// ==========================================
// DROPDOWN NUTRIENTS
// ==========================================

function toggleDropdown() {
    document.getElementById("myDropdown").classList.toggle("show");
}

// Ajout dynamique de nutriments compatibles PHP
function addNewElement() {
    const input = document.getElementById('newItemInput');
    const value = input.value.trim();
    const container = document.getElementById('myDropdown');
    const addZone = document.getElementById('addZone');

    if (value !== "") {
        const newLabel = document.createElement('label');
        newLabel.className = 'option-row';
        // Note : On utilise le nom saisi pour la clé du tableau des valeurs
        newLabel.innerHTML = `
            <input type="checkbox" name="nutriments[]" value="${value}" class="option-checkbox" checked>
            <span>${value}</span>
            <input type="number" name="valeurs[${value}]" class="percent-input" placeholder="%" min="0" max="100" style="color: #000; border: 1px solid #000;">
        `;
        container.insertBefore(newLabel, addZone);
        input.value = "";
    }
}

// Empêcher fermeture au clic intérieur
document.addEventListener('DOMContentLoaded', function() {
    const dropdown = document.getElementById("myDropdown");
    if (dropdown) {
        dropdown.addEventListener('click', (e) => e.stopPropagation());
    }
});


// ==========================================
// GÉNÉRATION DE PLANTE COMPLÈTE
// ==========================================

// Générer une plante complète avec 3 recettes
async function generatePlant() {
    const variety = document.getElementById('plantVariety').value.trim();
    
    if (!variety) {
        showPlantError('Veuillez entrer le nom d\'une variété de plante.');
        return;
    }
    
    // Afficher le loading
    document.getElementById('plantLoading').style.display = 'block';
    document.getElementById('plantResult').style.display = 'none';
    document.getElementById('plantError').style.display = 'none';
    document.getElementById('plantSuccess').style.display = 'none';
    document.getElementById('generatePlantBtn').disabled = true;
    
    try {
        const response = await fetch('/handlers/api_gemini_plant.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ variety: variety })
        });
        
        const data = await response.json();
        
        if (data.error) {
            showPlantError(data.error);
            return;
        }
        
        if (data.success && data.plant) {
            currentPlant = data.plant;
            displayPlantPreview(data.plant);
        } else {
            showPlantError('Impossible de générer la plante. Veuillez réessayer.');
        }
        
    } catch (error) {
        showPlantError('Erreur de connexion: ' + error.message);
    } finally {
        document.getElementById('plantLoading').style.display = 'none';
        document.getElementById('generatePlantBtn').disabled = false;
    }
}

// Afficher la prévisualisation de la plante
function displayPlantPreview(plant) {
    document.getElementById('plantVarietyName').textContent = plant.variety;
    
    // Germination
    const germ = plant.germination_recipe;
    document.getElementById('germName').textContent = germ.name;
    document.getElementById('germDuration').textContent = germ.duration;
    document.getElementById('germWater').textContent = germ.water_quantity;
    displayNutrients('germNutrients', germ.nutrients);
    
    // Végétative
    const veg = plant.vegetative_recipe;
    document.getElementById('vegName').textContent = veg.name;
    document.getElementById('vegDuration').textContent = veg.duration;
    document.getElementById('vegWater').textContent = veg.water_quantity;
    displayNutrients('vegNutrients', veg.nutrients);
    
    // Floraison
    const flower = plant.flowering_recipe;
    document.getElementById('flowerName').textContent = flower.name;
    document.getElementById('flowerDuration').textContent = flower.duration;
    document.getElementById('flowerWater').textContent = flower.water_quantity;
    displayNutrients('flowerNutrients', flower.nutrients);
    
    document.getElementById('plantResult').style.display = 'block';
    document.getElementById('plantError').style.display = 'none';
}

// Afficher les nutriments d'une recette
function displayNutrients(elementId, nutrients) {
    const container = document.getElementById(elementId);
    container.innerHTML = nutrients.map(n => 
        `<span class="nutrient-badge">${n.name} ${n.percentage}%</span>`
    ).join('');
}

// Sauvegarder la plante complète
async function savePlant() {
    if (!currentPlant) {
        showPlantError('Aucune plante à sauvegarder.');
        return;
    }
    
    const saveBtn = document.querySelector('.btn-save-plant');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
    
    try {
        const response = await fetch('/handlers/sauvegarder_plante_ia.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ plant: currentPlant })
        });
        
        const data = await response.json();
        
        if (data.error) {
            showPlantError(data.error);
        } else if (data.success) {
            showPlantSuccess(data.message);
            // Recharger la page après 2 secondes
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
        
    } catch (error) {
        showPlantError('Erreur lors de l\'enregistrement: ' + error.message);
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer cette plante et ses recettes';
    }
}

// Afficher erreur plante
function showPlantError(message) {
    const errorDiv = document.getElementById('plantError');
    errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + message;
    errorDiv.style.display = 'block';
    document.getElementById('plantSuccess').style.display = 'none';
}

// Afficher succès plante
function showPlantSuccess(message) {
    const successDiv = document.getElementById('plantSuccess');
    successDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
    successDiv.style.display = 'block';
    document.getElementById('plantError').style.display = 'none';
    document.getElementById('plantResult').style.display = 'none';
}

// Permettre l'envoi avec Entrée pour la plante
document.addEventListener('DOMContentLoaded', function() {
    const plantVariety = document.getElementById('plantVariety');
    if (plantVariety) {
        plantVariety.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                generatePlant();
            }
        });
    }
});
