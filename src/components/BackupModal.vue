<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Download, Upload, X, RotateCcw, Archive, Clock } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  allTags: Array
})

const emit = defineEmits(['close', 'imported', 'revived'])

const API_URL = import.meta.env.DEV ? 'http://localhost:8000/api' : '/api/index.php'
const isLoading = ref(false)
const message = ref('')
const messageType = ref('')
const fileInput = ref(null)
const activeTab = ref('backup')
const changelog = ref([])
const archivedTodos = ref([])
const undoInProgress = ref({})
const expandedChanges = ref({})

axios.defaults.withCredentials = true

onMounted(() => {
  if (activeTab.value === 'history') fetchChangelog()
  if (activeTab.value === 'archive') fetchArchive()
})

const switchTab = (tab) => {
  activeTab.value = tab
  if (tab === 'history') fetchChangelog()
  if (tab === 'archive') fetchArchive()
}

const fetchChangelog = async () => {
  try {
    const response = await axios.get(`${API_URL}/changelog`)
    changelog.value = response.data.changes || []
  } catch (error) {
    console.error('Error fetching changelog:', error)
    changelog.value = []
  }
}

const fetchArchive = async () => {
  try {
    const res = await axios.get(`${API_URL}/archive`)
    archivedTodos.value = res.data.archivedTodos || []
  } catch (err) { console.error("Error fetching archive", err) }
}

const getActionLabel = (change) => {
  const actionMap = {
    'created': '✨ Erstellt',
    'updated': '✏️ Bearbeitet',
    'deleted': '🗑️ Gelöscht',
    'archived': '📦 Archiviert',
    'restored': '↩️ Wiederhergestellt',
    'imported': '📥 Importiert'
  }
  return actionMap[change.action] || change.action
}

const getChangeDescription = (change) => {
  return change.description || change.todoName || 'Unbekannter Eintrag'
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  return date.toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}

const toggleExpanded = (changeId) => {
  expandedChanges.value[changeId] = !expandedChanges.value[changeId]
}

const downloadBackup = async () => {
  try {
    const res = await axios.get(`${API_URL}/backup/export`)
    const blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `todo-backup-${new Date().toISOString().split('T')[0]}.json`
    a.click()
    URL.revokeObjectURL(url)
    message.value = 'Backup erfolgreich heruntergeladen.'
    messageType.value = 'success'
  } catch (err) {
    message.value = 'Fehler beim Herunterladen des Backups.'
    messageType.value = 'error'
  }
}

const triggerFileInput = () => {
  fileInput.value.click()
}

const handleFileImport = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  try {
    const reader = new FileReader()
    reader.onload = async (e) => {
      try {
        const backup = JSON.parse(e.target.result)
        isLoading.value = true
        const res = await axios.post(`${API_URL}/backup/import`, backup)
        message.value = `Erfolgreich importiert! (${res.data.skippedCount} Duplikate übersprungen)`
        messageType.value = 'success'
        emit('imported')
        await fetchChangelog()
      } catch (err) {
        message.value = 'Fehler beim Importieren: ' + (err.response?.data?.message || err.message)
        messageType.value = 'error'
      } finally {
        isLoading.value = false
      }
    }
    reader.readAsText(file)
  } catch (error) {
    message.value = `❌ Fehler beim Importieren: ${error.message}`
    messageType.value = 'error'
  } finally {
    event.target.value = ''
  }
}

const reviveFromArchive = async (id) => {
  try {
    const archive = [...archivedTodos.value]
    const todoIndex = archive.findIndex(t => t.id === id)
    if (todoIndex === -1) return
    const todo = archive[todoIndex]
    archive.splice(todoIndex, 1)
    await axios.post(`${API_URL}/archive`, { archivedTodos: archive })
    const todosRes = await axios.get(`${API_URL}/data`)
    const todos = todosRes.data.todos || []
    todos.push(todo)
    await axios.post(`${API_URL}/todos`, { todos })
    message.value = 'Aufgabe erfolgreich wiederhergestellt!'
    messageType.value = 'success'
    await fetchArchive()
    emit('revived')
  } catch (err) {
    message.value = 'Fehler beim Wiederherstellen.'
    messageType.value = 'error'
  }
}

const undoChange = async (change) => {
  if (undoInProgress.value[change.id]) return
  undoInProgress.value[change.id] = true
  try {
    const response = await axios.post(`${API_URL}/changelog/undo`, { 
      todoId: change.todoId, 
      changeId: change.id 
    })
    if (response.data.success) {
      message.value = `✅ ${response.data.message}`
      messageType.value = 'success'
      await fetchChangelog()
      emit('imported')
    } else {
      message.value = `❌ ${response.data.message || 'Fehler'}`
      messageType.value = 'error'
    }
  } catch (error) {
    message.value = `❌ Fehler: ${error.message}`
    messageType.value = 'error'
  } finally {
    undoInProgress.value[change.id] = false
  }
}
</script>

<template>
  <div v-if="show" class="backup-modal-overlay" @click.self="emit('close')">
    <div class="backup-modal">
      <div class="modal-header">
        <h2>💾 Backup & Archiv</h2>
        <button class="close-btn" @click="emit('close')">
          <X :size="20" />
        </button>
      </div>

      <div class="tab-navigation">
        <button class="tab-btn" :class="{ active: activeTab === 'backup' }" @click="switchTab('backup')">
          <Download :size="16" /> Backup
        </button>
        <button class="tab-btn" :class="{ active: activeTab === 'archive' }" @click="switchTab('archive')">
          <Archive :size="16" /> Archiv
        </button>
        <button class="tab-btn" :class="{ active: activeTab === 'history' }" @click="switchTab('history')">
          <Clock :size="16" /> Historie
        </button>
      </div>

      <div class="modal-content">
        <div v-if="message" class="alert" :class="messageType">
          {{ message }}
        </div>

        <div v-show="activeTab === 'backup'" class="tab-content">
          <div class="backup-section">
            <div class="section-title"><Download :size="18" /> Exportieren</div>
            <p class="section-description">Laden Sie Ihre Todos inkl. Archiv und Einstellungen herunter.</p>
            <button class="backup-button export-btn" @click="downloadBackup" :disabled="isLoading">
              <Download :size="16" /> Backup herunterladen
            </button>
          </div>
          <div class="backup-section">
            <div class="section-title"><Upload :size="18" /> Importieren</div>
            <p class="section-description">Laden Sie eine Backup-JSON-Datei hoch. Duplikate werden übersprungen.</p>
            <input ref="fileInput" type="file" accept=".json" class="hidden-file-input" @change="handleFileImport" />
            <button class="backup-button import-btn" @click="triggerFileInput" :disabled="isLoading">
              <Upload :size="16" /> Datei wählen
            </button>
          </div>
        </div>

        <div v-show="activeTab === 'archive'" class="tab-content archive-tab">
          <div v-if="archivedTodos.length === 0" class="empty-state">Keine archivierten Aufgaben.</div>
          <div v-else class="archive-list">
            <div v-for="todo in archivedTodos" :key="todo.id" class="archive-item card slim">
              <div class="archive-info">
                <strong>{{ todo.name }}</strong>
                <div class="archive-meta">Archiviert am: {{ new Date(todo.id).toLocaleDateString() }}</div>
              </div>
              <button class="pure-button mini-btn" @click="reviveFromArchive(todo.id)" title="Wiederherstellen">
                <RotateCcw :size="14" />
              </button>
            </div>
          </div>
        </div>

        <div v-show="activeTab === 'history'" class="tab-content history-tab">
          <div v-if="changelog.length === 0" class="empty-state">Keine Änderungen vorhanden.</div>
          <div v-else class="changelog-list">
            <div v-for="change in changelog" :key="change.id" class="changelog-entry card slim">
              <div class="changelog-main" @click="toggleExpanded(change.id)">
                <div class="changelog-header">
                  <span class="action-badge">{{ getActionLabel(change) }}</span>
                  <span class="changelog-date">{{ formatDate(change.timestamp) }}</span>
                </div>
                <div class="changelog-summary">{{ getChangeDescription(change) }}</div>
              </div>
              <button class="undo-btn" @click.stop="undoChange(change)" :disabled="undoInProgress[change.id]">
                <RotateCcw :size="14" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.backup-modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0, 0, 0, 0.5); display: flex; align-items: center;
  justify-content: center; z-index: 2000; backdrop-filter: blur(4px);
}
.backup-modal {
  background: white; width: 90%; max-width: 600px; max-height: 85vh;
  border-radius: 1rem; display: flex; flex-direction: column; overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}
.modal-header {
  padding: 1.5rem; border-bottom: 1px solid #f3f4f6; display: flex;
  justify-content: space-between; align-items: center;
}
.modal-header h2 { margin: 0; font-size: 1.25rem; color: #1f2937; }
.close-btn { background: none; border: none; cursor: pointer; color: #9ca3af; }
.tab-navigation {
  display: flex; background: #f9fafb; padding: 0.5rem; gap: 0.5rem;
  border-bottom: 1px solid #f3f4f6;
}
.tab-btn {
  flex: 1; padding: 0.75rem; border: none; background: none; border-radius: 0.5rem;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  gap: 0.5rem; font-size: 0.875rem; color: #6b7280; transition: all 0.2s;
}
.tab-btn.active { background: white; color: #2563eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); font-weight: 600; }
.modal-content { padding: 1.5rem; overflow-y: auto; flex: 1; }
.backup-section { margin-bottom: 2rem; }
.section-title { display: flex; align-items: center; gap: 0.5rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
.section-description { font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem; }
.backup-button { width: 100%; padding: 0.75rem; border-radius: 0.5rem; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-weight: 500; transition: all 0.2s; }
.export-btn { background: #eff6ff; color: #2563eb; }
.export-btn:hover { background: #dbeafe; }
.import-btn { background: #f0fdf4; color: #16a34a; }
.import-btn:hover { background: #dcfce7; }
.hidden-file-input { display: none; }
.alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; }
.alert.success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.alert.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.archive-list, .changelog-list { display: flex; flex-direction: column; gap: 0.75rem; }
.archive-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; }
.archive-info strong { display: block; color: #1f2937; }
.archive-meta { font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem; }
.changelog-entry { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; }
.changelog-main { flex: 1; cursor: pointer; }
.changelog-header { display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.25rem; }
.action-badge { font-size: 0.7rem; font-weight: 600; padding: 0.1rem 0.4rem; border-radius: 1rem; background: #f3f4f6; color: #4b5563; }
.changelog-date { font-size: 0.75rem; color: #9ca3af; }
.changelog-summary { font-size: 0.875rem; color: #374151; font-weight: 500; }
.undo-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 0.5rem; border-radius: 0.375rem; }
.undo-btn:hover { background: #fef2f2; color: #ef4444; }
.empty-state { text-align: center; color: #9ca3af; padding: 2rem; font-style: italic; }
</style>
