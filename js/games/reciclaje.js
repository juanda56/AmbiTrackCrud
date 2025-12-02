document.addEventListener('DOMContentLoaded', function() {
    // Elementos del DOM
    const trashItemsContainer = document.getElementById('trash-items');
    const scoreElement = document.getElementById('score');
    const totalElement = document.getElementById('total');
    const feedbackElement = document.getElementById('feedback');
    const restartButton = document.getElementById('restart-btn');
    
    // Contenedores de basura
    const bins = {
        'organic': document.getElementById('bin-organic'),
        'plastic': document.getElementById('bin-plastic'),
        'paper': document.getElementById('bin-paper'),
        'glass': document.getElementById('bin-glass')
    };
    
    // Puntuación
    let score = 0;
    let totalItems = 0;
    
    // Tipos de basura y sus contenedores correspondientes
    const trashItems = [
        { type: 'organic', name: 'Manzana', icon: '🍎' },
        { type: 'organic', name: 'Plátano', icon: '🍌' },
        { type: 'plastic', name: 'Botella', icon: '🧴' },
        { type: 'plastic', name: 'Bolsa', icon: '🛍️' },
        { type: 'paper', name: 'Periódico', icon: '📰' },
        { type: 'paper', name: 'Caja', icon: '📦' },
        { type: 'glass', name: 'Botella de vidrio', icon: '🍾' },
        { type: 'glass', name: 'Tarro', icon: '🥫' }
    ];
    
    // Inicializar el juego
    function initGame() {
        // Reiniciar puntuación
        score = 0;
        totalItems = trashItems.length;
        updateScore();
        
        // Limpiar contenedor de elementos
        trashItemsContainer.innerHTML = '';
        
        // Mezclar elementos
        const shuffledItems = [...trashItems].sort(() => Math.random() - 0.5);
        
        // Crear elementos de basura
        shuffledItems.forEach((item, index) => {
            const trashElement = document.createElement('div');
            trashElement.className = 'trash-item d-flex flex-column align-items-center justify-content-center';
            trashElement.draggable = true;
            trashElement.dataset.type = item.type;
            trashElement.dataset.name = item.name;
            trashElement.innerHTML = `
                <div style="font-size: 2.5rem;">${item.icon}</div>
                <div style="font-size: 0.8rem; text-align: center;">${item.name}</div>
            `;
            
            // Eventos de arrastrar
            trashElement.addEventListener('dragstart', handleDragStart);
            
            trashItemsContainer.appendChild(trashElement);
        });
        
        // Añadir eventos a los contenedores
        Object.keys(bins).forEach(binType => {
            const bin = bins[binType];
            
            bin.addEventListener('dragover', handleDragOver);
            bin.addEventListener('dragenter', handleDragEnter);
            bin.addEventListener('dragleave', handleDragLeave);
            bin.addEventListener('drop', (e) => handleDrop(e, binType));
        });
        
        // Reiniciar feedback
        feedbackElement.textContent = '';
        feedbackElement.className = 'feedback';
    }
    
    // Manejadores de eventos de arrastrar y soltar
    function handleDragStart(e) {
        e.dataTransfer.setData('text/plain', e.target.dataset.type + '|' + e.target.dataset.name);
        setTimeout(() => {
            e.target.classList.add('opacity-25');
        }, 0);
    }
    
    function handleDragOver(e) {
        e.preventDefault();
    }
    
    function handleDragEnter(e) {
        e.preventDefault();
        this.classList.add('over');
    }
    
    function handleDragLeave() {
        this.classList.remove('over');
    }
    
    function handleDrop(e, binType) {
        e.preventDefault();
        this.classList.remove('over');
        
        const data = e.dataTransfer.getData('text/plain').split('|');
        const itemType = data[0];
        const itemName = data[1];
        const draggable = document.querySelector(`[data-name="${itemName}"]`);
        
        if (!draggable) return;
        
        // Verificar si la clasificación es correcta
        if (itemType === binType) {
            // Correcto
            score++;
            showFeedback(`¡Correcto! ${itemName} va en el contenedor de ${getBinName(binType)}.`, 'correct');
            
            // Animación de éxito
            draggable.style.transition = 'all 0.5s';
            draggable.style.transform = 'scale(0)';
            setTimeout(() => {
                draggable.remove();
            }, 500);
        } else {
            // Incorrecto
            showFeedback(`Incorrecto. ${itemName} no va en el contenedor de ${getBinName(binType)}.`, 'incorrect');
            draggable.classList.remove('opacity-25');
        }
        
        // Actualizar puntuación
        updateScore();
        
        // Verificar si el juego ha terminado
        checkGameOver();
    }
    
    // Funciones auxiliares
    function updateScore() {
        scoreElement.textContent = score;
        totalElement.textContent = totalItems;
    }
    
    function showFeedback(message, type) {
        feedbackElement.textContent = message;
        feedbackElement.className = 'feedback ' + type;
    }
    
    function getBinName(binType) {
        const names = {
            'organic': 'orgánicos',
            'plastic': 'plástico',
            'paper': 'papel',
            'glass': 'vidrio'
        };
        return names[binType] || binType;
    }
    
    function checkGameOver() {
        const remainingItems = document.querySelectorAll('.trash-item').length;
        if (remainingItems === 0) {
            const percentage = Math.round((score / totalItems) * 100);
            let message = `¡Juego terminado! Puntuación: ${score} de ${totalItems} (${percentage}%)`;
            
            if (percentage === 100) {
                message += ' ¡Perfecto! Eres un experto en reciclaje.';
            } else if (percentage >= 75) {
                message += ' ¡Muy bien! Casi lo tienes dominado.';
            } else if (percentage >= 50) {
                message += ' No está mal, pero puedes mejorar.';
            } else {
                message += ' Sigue practicando para mejorar.';
            }
            
            showFeedback(message, percentage >= 75 ? 'correct' : 'incorrect');
        }
    }
    
    // Evento para reiniciar el juego
    restartButton.addEventListener('click', initGame);
    
    // Iniciar el juego
    initGame();
});
