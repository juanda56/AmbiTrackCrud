document.addEventListener('DOMContentLoaded', function() {
    // Elementos del DOM
    const questionContainer = document.getElementById('question-container');
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    const finishBtn = document.getElementById('finish-btn');
    const restartBtn = document.getElementById('restart-btn');
    const feedbackElement = document.getElementById('feedback');
    const progressBar = document.getElementById('progress-bar');
    const currentQuestionEl = document.getElementById('current-question');
    const totalQuestionsEl = document.getElementById('total-questions');
    const carbonScoreEl = document.getElementById('carbon-score');
    const resultContainer = document.getElementById('result-container');
    const resultIcon = document.querySelector('.result-icon');
    const resultTitle = document.getElementById('result-title');
    const resultDescription = document.getElementById('result-description');
    const resultTips = document.getElementById('result-tips');

    // Preguntas del cuestionario
    const questions = [
        {
            question: '¿Cómo te transportas habitualmente?',
            options: [
                { text: 'Coche privado', value: 3 },
                { text: 'Transporte público', value: 1 },
                { text: 'Bicicleta o caminando', value: 0 },
                { text: 'Motocicleta', value: 2 }
            ]
        },
        {
            question: '¿Cuántos kilómetros recorres en transporte motorizado al día?',
            options: [
                { text: 'Menos de 5 km', value: 1 },
                { text: 'Entre 5 y 20 km', value: 2 },
                { text: 'Más de 20 km', value: 3 },
                { text: 'No uso transporte motorizado', value: 0 }
            ]
        },
        {
            question: '¿Qué tipo de dieta sigues?',
            options: [
                { text: 'Alta en carne roja', value: 3 },
                { text: 'Pollo y pescado, poca carne roja', value: 2 },
                { text: 'Vegetariana', value: 1 },
                { text: 'Vegana', value: 0 }
            ]
        },
        {
            question: '¿Cómo es el consumo de energía en tu hogar?',
            options: [
                { text: 'Uso energías renovables', value: 0 },
                { text: 'Electricidad convencional', value: 2 },
                { text: 'Uso moderado de calefacción/aire acondicionado', value: 1 },
                { text: 'Alto consumo de calefacción/aire acondicionado', value: 3 }
            ]
        },
        {
            question: '¿Con qué frecuencia compras productos nuevos?',
            options: [
                { text: 'Solo cuando es necesario', value: 0 },
                { text: 'Algunas veces al mes', value: 1 },
                { text: 'Varias veces al mes', value: 2 },
                { text: 'Semanalmente', value: 3 }
            ]
        }
    ];

    // Variables del juego
    let currentQuestion = 0;
    let score = 0;
    const maxScore = questions.length * 3; // Puntuación máxima posible
    const answers = new Array(questions.length).fill(null);

    // Inicializar el juego
    function initGame() {
        currentQuestion = 0;
        score = 0;
        answers.fill(null);
        
        // Actualizar interfaz
        totalQuestionsEl.textContent = questions.length;
        updateScore();
        showQuestion();
        
        // Ocultar resultados y mostrar preguntas
        resultContainer.style.display = 'none';
        document.getElementById('question-container').style.display = 'block';
        
        // Actualizar botones
        updateNavigationButtons();
    }

    // Mostrar la pregunta actual
    function showQuestion() {
        const question = questions[currentQuestion];
        
        // Actualizar contador de preguntas
        currentQuestionEl.textContent = currentQuestion + 1;
        
        // Actualizar barra de progreso
        const progress = ((currentQuestion) / questions.length) * 100;
        progressBar.style.width = `${progress}%`;
        
        // Construir el HTML de la pregunta
        let html = `
            <div class="question-container">
                <h4>${question.question}</h4>
                <div class="options-container mt-4">
        `;
        
        // Añadir opciones de respuesta
        question.options.forEach((option, index) => {
            const isSelected = answers[currentQuestion] === index ? 'selected' : '';
            html += `
                <div class="option ${isSelected}" data-index="${index}">
                    ${option.text}
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
        
        questionContainer.innerHTML = html;
        
        // Añadir eventos a las opciones
        document.querySelectorAll('.option').forEach(option => {
            option.addEventListener('click', selectOption);
        });
        
        // Actualizar botones de navegación
        updateNavigationButtons();
    }
    
    // Manejar selección de opción
    function selectOption(e) {
        const selectedOption = e.currentTarget;
        const optionIndex = parseInt(selectedOption.dataset.index);
        
        // Desmarcar otras opciones
        document.querySelectorAll('.option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        // Marcar opción seleccionada
        selectedOption.classList.add('selected');
        
        // Guardar respuesta
        answers[currentQuestion] = optionIndex;
        
        // Mostrar feedback inmediato
        showFeedback(questions[currentQuestion].options[optionIndex].value);
    }
    
    // Mostrar feedback
    function showFeedback(points) {
        feedbackElement.style.display = 'block';
        
        if (points === 0) {
            feedbackElement.textContent = '¡Excelente! Esta es la opción más sostenible.';
            feedbackElement.className = 'feedback correct';
        } else if (points <= 1) {
            feedbackElement.textContent = 'Buena elección, pero hay opciones más sostenibles.';
            feedbackElement.className = 'feedback correct';
        } else if (points <= 2) {
            feedbackElement.textContent = 'Hay opciones más amigables con el medio ambiente.';
            feedbackElement.className = 'feedback incorrect';
        } else {
            feedbackElement.textContent = 'Esta opción tiene un alto impacto ambiental. Considera alternativas más sostenibles.';
            feedbackElement.className = 'feedback incorrect';
        }
    }
    
    // Actualizar puntuación
    function updateScore() {
        // Calcular puntuación actual
        let currentScore = 0;
        answers.forEach((answer, index) => {
            if (answer !== null) {
                currentScore += questions[index].options[answer].value;
            }
        });
        
        score = currentScore;
        carbonScoreEl.textContent = score;
    }
    
    // Actualizar botones de navegación
    function updateNavigationButtons() {
        // Botón Anterior
        prevBtn.disabled = currentQuestion === 0;
        
        // Botón Siguiente/Ver resultados
        const isLastQuestion = currentQuestion === questions.length - 1;
        nextBtn.style.display = isLastQuestion ? 'none' : 'inline-block';
        finishBtn.style.display = isLastQuestion ? 'inline-block' : 'none';
        
        // Deshabilitar Siguiente si no hay respuesta seleccionada
        if (currentQuestion < questions.length) {
            nextBtn.disabled = answers[currentQuestion] === null;
            finishBtn.disabled = answers[currentQuestion] === null;
        }
    }
    
    // Mostrar resultados
    function showResults() {
        // Calcular porcentaje de sostenibilidad
        const percentage = 100 - Math.round((score / maxScore) * 100);
        
        // Configurar resultados según el puntaje
        if (percentage >= 80) {
            resultIcon.innerHTML = '🌱';
            resultTitle.textContent = '¡Eco-Héroe!';
            resultDescription.textContent = `Tu huella de carbono es muy baja (${percentage}% de sostenibilidad).`;
            resultTips.innerHTML = '¡Excelente trabajo! Sigue así y comparte tus prácticas sostenibles con los demás.';
        } else if (percentage >= 60) {
            resultIcon.innerHTML = '🌍';
            resultTitle.textContent = '¡Buen trabajo!';
            resultDescription.textContent = `Tu huella de carbono es moderada (${percentage}% de sostenibilidad).`;
            resultTips.innerHTML = 'Con algunos pequeños cambios podrías reducir aún más tu impacto ambiental.';
        } else if (percentage >= 40) {
            resultIcon.innerHTML = '🔍';
            resultTitle.textContent = 'Hay margen de mejora';
            resultDescription.textContent = `Tu huella de carbono es alta (${percentage}% de sostenibilidad).`;
            resultTips.innerHTML = 'Considera hacer cambios en tus hábitos de transporte, consumo de energía y alimentación para reducir tu impacto.';
        } else {
            resultIcon.innerHTML = '⚠️';
            resultTitle.textContent = '¡Alto impacto ambiental!';
            resultDescription.textContent = `Tu huella de carbono es muy alta (${percentage}% de sostenibilidad).`;
            resultTips.innerHTML = 'Es importante que consideres hacer cambios significativos en tu estilo de vida para reducir tu impacto en el planeta.';
        }
        
        // Mostrar sección de resultados y ocultar preguntas
        document.getElementById('question-container').style.display = 'none';
        resultContainer.style.display = 'block';
        
        // Desplazarse a la parte superior de la página
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    // Event Listeners
    nextBtn.addEventListener('click', () => {
        if (currentQuestion < questions.length - 1) {
            currentQuestion++;
            showQuestion();
        }
    });
    
    prevBtn.addEventListener('click', () => {
        if (currentQuestion > 0) {
            currentQuestion--;
            showQuestion();
        }
    });
    
    finishBtn.addEventListener('click', showResults);
    restartBtn.addEventListener('click', initGame);
    
    // Inicializar el juego
    initGame();
});
