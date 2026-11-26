document.addEventListener('DOMContentLoaded', () => {
  const dataEl = document.getElementById('dashboard-data');
  if (!dataEl || typeof Chart === 'undefined') return;
  let payload;
  try { payload = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
  const common = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };

  const balanceEl = document.getElementById('balanceChart');
  if (balanceEl && payload.balance3) {
    new Chart(balanceEl, {
      type: 'doughnut',
      data: {
        labels: payload.balance3.labels || [],
        datasets: [{ data: payload.balance3.values || [], backgroundColor: ['#8b1e1e', '#2563eb', '#059669'], borderWidth: 0 }]
      },
      options: common
    });
  }

  const statusEl = document.getElementById('statusChart');
  if (statusEl && payload.statusChart) {
    new Chart(statusEl, {
      type: 'bar',
      data: {
        labels: payload.statusChart.labels || [],
        datasets: [
          { label: 'Completado/Atendido', data: payload.statusChart.completado || [], backgroundColor: '#166534', borderRadius: 6 },
          { label: 'En proceso', data: payload.statusChart.proceso || [], backgroundColor: '#d97706', borderRadius: 6 },
          { label: 'Pendiente/Falta', data: payload.statusChart.pendiente || [], backgroundColor: '#b91c1c', borderRadius: 6 }
        ]
      },
      options: { ...common, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true } } }
    });
  }
});
