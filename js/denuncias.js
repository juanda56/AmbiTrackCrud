// Clase para manejar el CRUD de denuncias
class GestorDenuncias {
    constructor() {
        this.denuncias = JSON.parse(localStorage.getItem('denuncias')) || [];
        this.denunciaActual = null;
        this.inicializarEventos();
        this.mostrarDenuncias();
        this.inicializarTooltips();
    }

    // Inicializar eventos del formulario y botones
    inicializarEventos() {
        // Guardar denuncia
        document.getElementById('denunciaForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.guardarDenuncia();
        });

        // Cancelar edición
        document.getElementById('btnCancelar').addEventListener('click', () => {
            this.cancelarEdicion();
        });

        // Buscar denuncias
        document.getElementById('buscarDenuncia').addEventListener('input', (e) => {
            this.buscarDenuncias(e.target.value);
        });

        // Botón de filtro
        document.querySelector('[data-bs-toggle="filter"]')?.addEventListener('click', (e) => {
            e.preventDefault();
            // Aquí se puede implementar la lógica de filtrado avanzado
            console.log('Filtro avanzado');
        });

        // Confirmar eliminación
        document.getElementById('confirmDelete').addEventListener('click', () => {
            if (this.denunciaActual) {
                this.eliminarDenuncia(this.denunciaActual);
                this.cerrarModal('confirmModal');
            }
        });
        
        // Mostrar nombre del archivo al seleccionar
        document.getElementById('evidencia')?.addEventListener('change', (e) => {
            const nombreArchivo = document.getElementById('nombreArchivo');
            if (e.target.files.length > 0) {
                nombreArchivo.textContent = e.target.files[0].name;
                nombreArchivo.classList.add('text-success', 'fw-bold');
            } else {
                nombreArchivo.textContent = '';
                nombreArchivo.classList.remove('text-success', 'fw-bold');
            }
        });
    }

    // Obtener el siguiente ID disponible
    obtenerSiguienteId() {
        return this.denuncias.length > 0 
            ? Math.max(...this.denuncias.map(d => d.id)) + 1 
            : 1;
    }

    // Guardar o actualizar una denuncia
    guardarDenuncia() {
        const id = document.getElementById('denunciaId').value;
        const tipo = document.getElementById('tipo').value;
        const fecha = document.getElementById('fecha').value;
        const ubicacion = document.getElementById('ubicacion').value;
        const descripcion = document.getElementById('descripcion').value;
        const evidencia = document.getElementById('evidencia').files[0];
        
        // Validar formulario
        if (!tipo || !fecha || !ubicacion || !descripcion) {
            this.mostrarAlerta('Por favor complete todos los campos obligatorios', 'danger');
            return;
        }

        const denuncia = {
            id: id ? parseInt(id) : this.obtenerSiguienteId(),
            tipo,
            fecha,
            ubicacion,
            descripcion,
            estado: 'Pendiente',
            fechaRegistro: new Date().toISOString()
        };

        // Procesar evidencia si existe
        if (evidencia) {
            if (evidencia.size > 5 * 1024 * 1024) { // 5MB
                this.mostrarAlerta('El archivo es demasiado grande. El tamaño máximo permitido es 5MB.', 'warning');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = (e) => {
                denuncia.evidencia = e.target.result;
                this.guardarEnLocalStorage(denuncia, id);
            };
            reader.readAsDataURL(evidencia);
        } else {
            this.guardarEnLocalStorage(denuncia, id);
        }
    }

    // Guardar en localStorage
    guardarEnLocalStorage(denuncia, id) {
        if (id) {
            // Actualizar denuncia existente
            const index = this.denuncias.findIndex(d => d.id === parseInt(id));
            if (index !== -1) {
                this.denuncias[index] = { ...this.denuncias[index], ...denuncia };
            }
        } else {
            // Agregar nueva denuncia
            this.denuncias.push(denuncia);
        }

        localStorage.setItem('denuncias', JSON.stringify(this.denuncias));
        this.mostrarAlerta(`Denuncia ${id ? 'actualizada' : 'registrada'} correctamente`, 'success');
        this.limpiarFormulario();
        this.mostrarDenuncias();
    }

    // Inicializar tooltips
    inicializarTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(tooltipTriggerEl => {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Mostrar denuncias en la tabla
    mostrarDenuncias(denuncias = this.denuncias) {
        const tbody = document.getElementById('tablaDenuncias');
        const totalDenuncias = denuncias.length;
        
        // Actualizar contador de resultados
        const contador = document.querySelector('.pagination-info');
        if (contador) {
            const mostrandoHasta = Math.min(10, totalDenuncias);
            contador.innerHTML = `Mostrando <span class="fw-semibold">1</span> a <span class="fw-semibold">${mostrandoHasta}</span> de <span class="fw-semibold">${totalDenuncias}</span> denuncias`;
        }
        
        if (totalDenuncias === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="py-4">
                            <div class="mb-3">
                                <i class="fas fa-inbox fa-4x text-muted opacity-25"></i>
                            </div>
                            <h5 class="text-muted mb-2">No hay denuncias registradas</h5>
                            <p class="text-muted small mb-0">Comienza agregando una nueva denuncia</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = denuncias.map(denuncia => {
            const fecha = new Date(denuncia.fecha).toLocaleDateString('es-MX', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            
            const fechaRegistro = new Date(denuncia.fechaRegistro).toLocaleString('es-MX', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            return `
                <tr class="align-middle">
                    <td class="ps-4 fw-semibold text-muted">#${denuncia.id.toString().padStart(3, '0')}</td>
                    <td class="ps-3">
                        <div class="d-flex align-items-center">
                            <div class="icon-shape bg-soft-${this.obtenerColorTipo(denuncia.tipo)} text-${this.obtenerColorTipo(denuncia.tipo)} rounded-3 p-2 me-2">
                                <i class="fas ${this.obtenerIconoTipo(denuncia.tipo)} fa-fw"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">${denuncia.tipo}</h6>
                                <small class="text-muted">${fechaRegistro}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="far fa-calendar-alt text-muted me-2"></i>
                            <span>${fecha}</span>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-map-marker-alt text-muted me-2"></i>
                            <span>${denuncia.ubicacion.split(',')[0]}</span>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge ${this.obtenerClaseEstado(denuncia.estado)} px-3 py-2">
                            <i class="${this.obtenerIconoEstado(denuncia.estado)} me-1"></i>
                            ${denuncia.estado}
                        </span>
                    </td>
                    <td class="text-end pe-4">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-icon btn-outline-primary" onclick="gestorDenuncias.cargarParaEditar(${denuncia.id})" data-bs-toggle="tooltip" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-icon btn-outline-danger" onclick="gestorDenuncias.mostrarModalConfirmacion(${denuncia.id})" data-bs-toggle="tooltip" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                            ${denuncia.evidencia ? `
                            <button class="btn btn-sm btn-icon btn-outline-info" onclick="gestorDenuncias.mostrarEvidencia('${denuncia.evidencia}')" data-bs-toggle="tooltip" title="Ver evidencia">
                                <i class="fas fa-image"></i>
                            </button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
        
        // Inicializar tooltips
        this.inicializarTooltips();
    }

    // Cargar denuncia para editar
    cargarParaEditar(id) {
        const denuncia = this.denuncias.find(d => d.id === id);
        if (!denuncia) return;

        this.denunciaActual = id;
        document.getElementById('denunciaId').value = denuncia.id;
        document.getElementById('tipo').value = denuncia.tipo;
        
        // Manejar formato de fecha
        let fecha = denuncia.fecha;
        if (fecha.includes('T')) {
            fecha = fecha.split('T')[0];
        }
        document.getElementById('fecha').value = fecha;
        
        document.getElementById('ubicacion').value = denuncia.ubicacion;
        document.getElementById('descripcion').value = denuncia.descripcion;
        
        // Actualizar UI
        const formTitle = document.querySelector('.modal-title');
        if (formTitle) {
            formTitle.textContent = 'Editar Denuncia';
        }
        
        const btnCancelar = document.getElementById('btnCancelar');
        if (btnCancelar) {
            btnCancelar.style.display = 'inline-flex';
        }
        
        // Mostrar nombre de archivo si existe evidencia
        const nombreArchivo = document.getElementById('nombreArchivo');
        if (denuncia.evidencia) {
            nombreArchivo.textContent = 'Evidencia cargada';
            nombreArchivo.classList.add('text-success', 'fw-bold');
        } else {
            nombreArchivo.textContent = 'Ningún archivo seleccionado';
            nombreArchivo.classList.remove('text-success', 'fw-bold');
        }
        
        // Desplazarse al formulario
        document.getElementById('denunciaForm').scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }

    // Eliminar denuncia
    eliminarDenuncia(id) {
        this.denuncias = this.denuncias.filter(d => d.id !== id);
        localStorage.setItem('denuncias', JSON.stringify(this.denuncias));
        this.mostrarAlerta('Denuncia eliminada correctamente', 'success');
        this.mostrarDenuncias();
    }

    // Buscar denuncias
    buscarDenuncias(termino) {
        if (!termino) {
            this.mostrarDenuncias();
            return;
        }

        const busqueda = termino.toLowerCase();
        const denunciasFiltradas = this.denuncias.filter(denuncia => 
            denuncia.tipo.toLowerCase().includes(busqueda) ||
            denuncia.ubicacion.toLowerCase().includes(busqueda) ||
            denuncia.descripcion.toLowerCase().includes(busqueda) ||
            denuncia.estado.toLowerCase().includes(busqueda)
        );

        this.mostrarDenuncias(denunciasFiltradas);
    }

    // Limpiar formulario
    limpiarFormulario() {
        const form = document.getElementById('denunciaForm');
        if (form) {
            form.reset();
        }
        
        const denunciaId = document.getElementById('denunciaId');
        if (denunciaId) {
            denunciaId.value = '';
        }
        
        const formTitle = document.querySelector('.modal-title');
        if (formTitle) {
            formTitle.textContent = 'Nueva Denuncia Ambiental';
        }
        
        const btnCancelar = document.getElementById('btnCancelar');
        if (btnCancelar) {
            btnCancelar.style.display = 'none';
        }
        
        const nombreArchivo = document.getElementById('nombreArchivo');
        if (nombreArchivo) {
            nombreArchivo.textContent = 'Ningún archivo seleccionado';
            nombreArchivo.classList.remove('text-success', 'fw-bold');
        }
        
        this.denunciaActual = null;
    }

    // Cancelar edición
    cancelarEdicion() {
        if (confirm('¿Desea cancelar la edición? Los cambios no guardados se perderán.')) {
            this.limpiarFormulario();
        }
    }

    // Mostrar modal de confirmación
    mostrarModalConfirmacion(id) {
        this.denunciaActual = id;
        const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        modal.show();
    }

    // Cerrar modal
    cerrarModal(modalId = 'confirmModal') {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) return;
        
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        } else {
            // Si no hay instancia, crear una nueva y ocultarla
            const newModal = new bootstrap.Modal(modalElement);
            newModal.hide();
        }
        
        // Limpiar el modal del DOM después de la animación
        modalElement.addEventListener('hidden.bs.modal', function() {
            if (modalId !== 'confirmModal') {
                modalElement.remove();
            }
        }, { once: true });
        
        this.denunciaActual = null;
    }

    // Mostrar alerta
    mostrarAlerta(mensaje, tipo) {
        const alerta = document.createElement('div');
        alerta.className = `alert alert-${tipo} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alerta.style.zIndex = '1100';
        alerta.style.maxWidth = '300px';
        alerta.innerHTML = `
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(alerta);
        
        // Eliminar la alerta después de 5 segundos
        setTimeout(() => {
            alerta.remove();
        }, 5000);
    }

    // Obtener clase CSS según el estado
    obtenerClaseEstado(estado) {
        const clases = {
            'Pendiente': 'bg-soft-warning text-warning',
            'En revisión': 'bg-soft-info text-info',
            'En proceso': 'bg-soft-primary text-primary',
            'Resuelta': 'bg-soft-success text-success',
            'Rechazada': 'bg-soft-danger text-danger'
        };
        return clases[estado] || 'bg-soft-secondary text-secondary';
    }

    // Mostrar evidencia
    mostrarEvidencia(src) {
        // Verificar si ya existe un modal de evidencia abierto
        let modalElement = document.getElementById('evidenciaModal');
        
        if (!modalElement) {
            // Crear el modal si no existe
            const modalHTML = `
                <div class="modal fade" id="evidenciaModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content bg-dark text-light">
                            <div class="modal-header border-0">
                                <h5 class="modal-title"><i class="fas fa-image me-2"></i>Evidencia</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center p-0 bg-black">
                                <img src="${src}" class="img-fluid" alt="Evidencia" style="max-height: 80vh;">
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const div = document.createElement('div');
            div.innerHTML = modalHTML;
            document.body.appendChild(div);
            modalElement = div.firstElementChild;
            
            // Eliminar el modal del DOM cuando se cierre
            modalElement.addEventListener('hidden.bs.modal', () => {
                setTimeout(() => {
                    if (document.body.contains(modalElement)) {
                        document.body.removeChild(modalElement.parentNode);
                    }
                }, 300);
            }, { once: true });
        } else {
            // Actualizar la imagen si el modal ya existe
            const img = modalElement.querySelector('img');
            if (img) img.src = src;
        }
        
        // Mostrar el modal
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
}

// Inicializar el gestor de denuncias cuando el DOM esté cargado
document.addEventListener('DOMContentLoaded', () => {
    window.gestorDenuncias = new GestorDenuncias();
});
