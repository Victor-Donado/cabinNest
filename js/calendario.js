const PRECIO_POR_NOCHE = 120;
  const nombresMeses = [
    "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
    "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
  ];

  // 1. OBTENER FECHA DE HOY SIN HORAS (medianoche exacta para comparar días)
  const fechaHoy = new Date();
  fechaHoy.setHours(0, 0, 0, 0);
  const tiempoHoy = fechaHoy.getTime();

  // 2. FECHAS YA RESERVADAS (Ejemplo usando Noviembre y Diciembre 2026)
  const reservasOcupadas = [
    { inicio: "2026-11-20", fin: "2026-11-22" },
    { inicio: "2026-11-27", fin: "2026-11-29" },
    { inicio: "2026-12-05", fin: "2026-12-08" }
  ];

  // Convierte texto "AAAA-MM-DD" a milisegundos en hora local
  function convertirFechaLocal(textoFecha) {
    const [anio, mes, dia] = textoFecha.split("-").map(Number);
    return new Date(anio, mes - 1, dia).getTime();
  }

  const rangosBloqueados = reservasOcupadas.map(reserva => ({
    inicio: convertirFechaLocal(reserva.inicio),
    fin: convertirFechaLocal(reserva.fin)
  }));

  // Verifica si un día específico ya está ocupado
  function estaReservado(tiempo) {
    return rangosBloqueados.some(rango => tiempo >= rango.inicio && tiempo <= rango.fin);
  }

  // Verifica si existen días ocupados dentro del rango que intenta elegir el usuario
  function hayReservaEnMedio(tiempoInicio, tiempoFin) {
    return rangosBloqueados.some(
      rango => rango.inicio <= tiempoFin && rango.fin >= tiempoInicio
    );
  }

  // Iniciamos el calendario en el mes y año actual
  let anioActual = fechaHoy.getFullYear();
  let mesActual = fechaHoy.getMonth();
  let fechaEntrada = null;
  let fechaSalida = null;

  // Referencias a elementos del HTML
  const botonMesAnterior = document.getElementById("mesAnterior");
  const botonMesSiguiente = document.getElementById("mesSiguiente");
  const etiquetaMes = document.getElementById("etiquetaMes");
  const cuadriculaDias = document.getElementById("cuadriculaDias");
  const cantidadNoches = document.getElementById("cantidadNoches");
  const precioTotal = document.getElementById("precioTotal");

  function dibujarCalendario() {
    etiquetaMes.textContent = `${nombresMeses[mesActual]} ${anioActual}`;
    cuadriculaDias.innerHTML = "";

    // Bloquea el botón de retroceder si estamos en el mes actual
    const esMesActual = (anioActual === fechaHoy.getFullYear() && mesActual === fechaHoy.getMonth());
    botonMesAnterior.disabled = esMesActual;

    const primerDiaSemana = new Date(anioActual, mesActual, 1).getDay();
    const diasEnMes = new Date(anioActual, mesActual + 1, 0).getDate();
    const diasMesAnterior = new Date(anioActual, mesActual, 0).getDate();

    // Relleno de días del mes anterior
    for (let i = primerDiaSemana - 1; i >= 0; i--) {
      const celda = document.createElement("div");
      celda.className = "celda-dia otro-mes";
      celda.textContent = diasMesAnterior - i;
      cuadriculaDias.appendChild(celda);
    }

    // Días del mes en curso
    for (let dia = 1; dia <= diasEnMes; dia++) {
      const celda = document.createElement("div");
      celda.className = "celda-dia";
      celda.textContent = dia;

      const tiempoCelda = new Date(anioActual, mesActual, dia).getTime();

      if (tiempoCelda < tiempoHoy) {
        // Día pasado: bloqueado
        celda.classList.add("pasado");
        celda.title = "Fecha pasada";
      } else if (estaReservado(tiempoCelda)) {
        // Día reservado: círculo naranja bloqueado
        celda.classList.add("reservado");
        celda.title = "Fecha no disponible";
      } else {
        // Día disponible para reservar
        if (fechaEntrada && tiempoCelda === fechaEntrada) {
          celda.classList.add("entrada");
        } else if (fechaSalida && tiempoCelda === fechaSalida) {
          celda.classList.add("salida");
        } else if (fechaEntrada && fechaSalida && tiempoCelda > fechaEntrada && tiempoCelda < fechaSalida) {
          celda.classList.add("en-rango");
        }

        celda.addEventListener("click", () => manejarClicDia(tiempoCelda));
      }

      cuadriculaDias.appendChild(celda);
    }

    // Relleno de días del mes siguiente
    const totalCeldas = primerDiaSemana + diasEnMes;
    const diasSiguientes = (7 - (totalCeldas % 7)) % 7;
    for (let i = 1; i <= diasSiguientes; i++) {
      const celda = document.createElement("div");
      celda.className = "celda-dia otro-mes";
      celda.textContent = i;
      cuadriculaDias.appendChild(celda);
    }

    actualizarResumen();
  }

  function manejarClicDia(tiempoSeleccionado) {
    if (
      !fechaEntrada ||
      (fechaEntrada && fechaSalida) ||
      tiempoSeleccionado <= fechaEntrada ||
      hayReservaEnMedio(fechaEntrada, tiempoSeleccionado)
    ) {
      fechaEntrada = tiempoSeleccionado;
      fechaSalida = null;
    } else {
      fechaSalida = tiempoSeleccionado;
    }
    dibujarCalendario();
  }

  function actualizarResumen() {
    if (fechaEntrada && fechaSalida) {
      const msPorDia = 1000 * 60 * 60 * 24;
      const noches = Math.round((fechaSalida - fechaEntrada) / msPorDia);
      cantidadNoches.textContent = noches;
      precioTotal.textContent = `Total: $${noches * PRECIO_POR_NOCHE}`;
    } else {
      cantidadNoches.textContent = "0";
      precioTotal.textContent = "Total: $0";
    }
  }

  botonMesAnterior.addEventListener("click", () => {
    mesActual--;
    if (mesActual < 0) {
      mesActual = 11;
      anioActual--;
    }
    dibujarCalendario();
  });

  botonMesSiguiente.addEventListener("click", () => {
    mesActual++;
    if (mesActual > 11) {
      mesActual = 0;
      anioActual++;
    }
    dibujarCalendario();
  });

  document.getElementById("botonConfirmar").addEventListener("click", () => {
    if (!fechaEntrada || !fechaSalida) {
      alert("Selecciona una fecha de entrada y una de salida disponibles.");
      return;
    }
    const entradaTexto = new Date(fechaEntrada).toLocaleDateString("es-ES");
    const salidaTexto = new Date(fechaSalida).toLocaleDateString("es-ES");
    const huespedes = document.getElementById("selectorHuespedes").value;

    alert(
      `Reserva lista:\n` +
      `• Entrada: ${entradaTexto}\n` +
      `• Salida: ${salidaTexto}\n` +
      `• Noches: ${cantidadNoches.textContent}\n` +
      `• Huéspedes: ${huespedes}\n` +
      `• ${precioTotal.textContent}`
    );
  });

  dibujarCalendario();