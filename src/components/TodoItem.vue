<script setup>
import { ref, watch } from 'vue'
import { GripVertical, Trash2, Calendar, Tag, ChevronDown, ChevronUp, Edit2, Check, X, ArchiveRestore, Circle, CheckCircle2 } from 'lucide-vue-next'
import Editor from './Editor.vue'

const props = defineProps({
  todo: Object,
  allTags: Array,
  canDrag: Boolean,
  isArchive: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update', 'delete', 'revive'])

const isEditingTitle = ref(false)
const isEditingTags = ref(false)
const isEditingDescription = ref(false)
const isExpanded = ref(false)
const isConfirmingDelete = ref(false)
const localTodo = ref({ ...props.todo })
const tagInput = ref(props.todo.tags ? props.todo.tags.join(', ') : '')

watch(() => props.todo, (newVal) => {
  localTodo.value = { ...newVal }
  tagInput.value = newVal.tags ? newVal.tags.join(', ') : ''
}, { deep: true })

const saveTitle = () => {
  emit('update', localTodo.value)
  isEditingTitle.value = false
}

const cancelTitle = () => {
  localTodo.value.name = props.todo.name
  isEditingTitle.value = false
}

const saveTags = () => {
  localTodo.value.tags = tagInput.value.split(',').map(s => s.trim()).filter(Boolean)
  emit('update', localTodo.value)
  isEditingTags.value = false
}

const cancelTags = () => {
  tagInput.value = props.todo.tags ? props.todo.tags.join(', ') : ''
  isEditingTags.value = false
}

const saveDescription = () => {
  emit('update', localTodo.value)
  isEditingDescription.value = false
}

const cancelDescription = () => {
  localTodo.value.description = props.todo.description
  isEditingDescription.value = false
}

const toggleStatus = () => {
  const newStatus = props.todo.status === 'erledigt' ? 'offen' : 'erledigt'
  emit('update', { ...props.todo, status: newStatus })
}

const updateDate = (newDate) => {
  emit('update', { ...props.todo, targetDate: newDate })
}

const cancel = () => {
  localTodo.value = { ...props.todo }
  tagInput.value = props.todo.tags ? props.todo.tags.join(', ') : ''
  isEditingTitle.value = false
  isEditingTags.value = false
  isEditingDescription.value = false
}

const addTagToInput = (tag) => {
  const currentTags = tagInput.value.split(',').map(s => s.trim()).filter(Boolean)
  if (!currentTags.includes(tag)) {
    currentTags.push(tag)
    tagInput.value = currentTags.join(', ')
  }
}

const formatDate = (dateStr) => {
  if (!dateStr || typeof dateStr !== 'string') return 'Datum'
  const parts = dateStr.split('-')
  if (parts.length !== 3) return 'Datum'
  const [year, month, day] = parts
  return `${day}.${month}.${year}`
}

const requestDelete = () => {
  isConfirmingDelete.value = true
}

const confirmDelete = () => {
  emit('delete')
  isConfirmingDelete.value = false
}

const cancelDelete = () => {
  isConfirmingDelete.value = false
}

const dateInputRef = ref(null)
const openPicker = () => {
  if (dateInputRef.value) {
    if (typeof dateInputRef.value.showPicker === 'function') {
      dateInputRef.value.showPicker()
    } else {
      dateInputRef.value.click()
    }
  }
}

</script>

<template>
  <div class="card todo-item" style="padding: 0; overflow: hidden; margin-bottom: 0.25rem;" :class="{ 'is-archive-item': isArchive }">
    <div class="main-row" @click="!isArchive && (isExpanded = !isExpanded)">
      <div v-if="canDrag && !isArchive" class="drag-handle">
        <GripVertical :size="14" />
      </div>

      <div class="content">
        <div class="view-mode">
          <div class="name-row">
            <span v-if="!isEditingTitle" class="name editable-text" :class="{ 'is-completed': todo.status === 'erledigt' }" @click.stop="isEditingTitle = true" title="Titel bearbeiten">{{ todo.name }}</span>
            <input v-else v-model="localTodo.name" class="pure-u-1 mini-input inline-title-input" @keyup.enter="saveTitle" @keyup.esc="cancelTitle" @blur="saveTitle" maxlength="150" autofocus @click.stop />
            
            <div class="meta-inline">
              <div v-if="!isEditingTags" class="tags-list editable-text" @click.stop="isEditingTags = true" title="Tags bearbeiten">
                <span v-if="!todo.tags || todo.tags.length === 0" class="badge empty-badge"><Tag :size="8"/> +</span>
                <span v-for="tag in todo.tags" :key="tag" class="badge">
                  <Tag :size="8" /> {{ tag }}
                </span>
              </div>
              <input v-else v-model="tagInput" class="mini-input inline-tag-input" @keyup.enter="saveTags" @keyup.esc="cancelTags" @blur="saveTags" placeholder="Tag1, Tag2" autofocus @click.stop />
              
              <div class="inline-date-picker" title="Datum bearbeiten" @click.stop="openPicker">
                <label class="badge date-badge editable-text" :class="{ 'no-date': !todo.targetDate }" style="pointer-events: none;">
                  <Calendar :size="8" /> {{ formatDate(todo.targetDate) }}
                </label>
                <input v-if="!isArchive" ref="dateInputRef" :id="'date-' + todo.id" type="date" class="hidden-date-input" :value="todo.targetDate" @change="(e) => { updateDate(e.target.value); e.target.blur(); }" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="actions">
        <template v-if="isArchive">
          <button class="pure-button btn-icon small btn-success" @click.stop="emit('revive')" title="Wiederherstellen"><ArchiveRestore :size="12" /></button>
        </template>
        <template v-else>
          <template v-if="isConfirmingDelete">
            <button class="pure-button btn-icon small btn-danger" @click.stop="confirmDelete" title="Wirklich löschen?"><Check :size="12" /></button>
            <button class="pure-button btn-icon small" @click.stop="cancelDelete" title="Abbrechen"><X :size="12" /></button>
          </template>
          <template v-else>
            <button class="pure-button btn-icon small" @click.stop="toggleStatus" :title="todo.status === 'erledigt' ? 'Als offen markieren' : 'Als erledigt markieren'">
              <CheckCircle2 v-if="todo.status === 'erledigt'" :size="14" class="text-success" />
              <Circle v-else :size="14" />
            </button>
            <button class="pure-button btn-icon small btn-danger" @click.stop="requestDelete" title="Löschen"><Trash2 :size="12" /></button>
            <button class="pure-button btn-icon small" @click.stop="isExpanded = !isExpanded">
              <ChevronDown v-if="!isExpanded" :size="14" />
              <ChevronUp v-else :size="14" />
            </button>
          </template>
        </template>
      </div>
    </div>

    <div v-if="isExpanded" class="expanded-content">
      <div class="description-section">
        <div v-if="!isEditingDescription" class="description-view editable-desc" @click="isEditingDescription = true" title="Klicken zum Bearbeiten" v-html="todo.description || '<i>Keine Beschreibung. Klicken zum Hinzufügen.</i>'"></div>
        <div v-else class="inline-editor-container">
          <Editor v-model="localTodo.description" />
          <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
            <button class="pure-button btn-success mini-btn" @click.stop="saveDescription"><Check :size="12"/> Speichern</button>
            <button class="pure-button mini-btn secondary" @click.stop="cancelDescription"><X :size="12"/> Abbrechen</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.todo-item { transition: all 0.1s; cursor: pointer; }
.main-row { display: flex; align-items: center; padding: 0.25rem 0.75rem; gap: 0.75rem; min-height: 2rem; }
.drag-handle { cursor: grab; color: #d1d5db; display: flex; }
.content { flex: 1; min-width: 0; }
.name-row { display: flex; align-items: center; gap: 0.75rem; }
.name { font-weight: 500; color: #111827; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; display: block; }
.name.is-completed { text-decoration: line-through; color: #9ca3af; }
.meta-inline { display: flex; gap: 0.4rem; align-items: center; }
.tags-list { display: flex; gap: 0.25rem; }
.badge { display: inline-flex; align-items: center; gap: 0.2rem; background: #f3f4f6; color: #4b5563; padding: 0.05rem 0.4rem; border-radius: 2rem; font-size: 0.65rem; cursor: pointer; border: 1px solid transparent; }
.date-badge { background: #fff7ed; color: #9a3412; border: 1px solid #ffedd5; }
.date-badge.no-date { background: #f9fafb; color: #9ca3af; border: 1px dashed #e5e7eb; }

.inline-date-picker { position: relative; display: inline-flex; align-items: center; cursor: pointer; min-width: 80px; min-height: 1.5rem; }
.hidden-date-input { position: absolute; opacity: 0; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; border: none; padding: 0; margin: 0; display: block; }

.actions { display: flex; gap: 0.1rem; }
.btn-icon.small { padding: 0.2rem; border-radius: 2rem !important; min-width: 1.5rem; height: 1.5rem; display: flex; justify-content: center; align-items: center; background: transparent; color: #9ca3af; }
.btn-icon.small:hover { background: #f3f4f6; color: #4b5563; }
.btn-danger:hover { color: var(--danger); background: #fee2e2; }
.btn-success:hover { color: var(--success); background: #d1fae5; }

.expanded-content { padding: 0.75rem; background: #f9fafb; border-top: 1px solid var(--border-color); }
.description-view { font-size: 0.85rem; color: #4b5563; line-height: 1.4; }
.editable-desc { padding: 0.5rem; border-radius: 0.25rem; border: 1px solid transparent; cursor: text; transition: all 0.2s; }
.editable-desc:hover, .editable-text:hover { background: white; border-color: #d1d5db; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.editable-text { cursor: text; padding: 0.1rem 0.25rem; border-radius: 0.25rem; border: 1px solid transparent; margin: -0.1rem -0.25rem; transition: all 0.2s; }
.inline-title-input { flex: 1; min-width: 150px; font-weight: 500; font-size: 0.9rem !important; }
.inline-tag-input { width: 120px; font-size: 0.75rem !important; padding: 0.1rem 0.3rem !important; }
.empty-badge { background: #e5e7eb; color: #6b7280; }
.text-success { color: #10b981; }

.mini-input { padding: 0.2rem 0.6rem !important; font-size: 0.85rem !important; }
.suggested-tags { display: flex; flex-wrap: wrap; gap: 0.2rem; margin-top: 0.25rem; }
.tag-chip.mini { font-size: 0.6rem; padding: 0.05rem 0.4rem; background: #f3f4f6; border-radius: 2rem; cursor: pointer; color: #6b7280; }

/* Responsive Design for Tablets and Smartphones */
@media (max-width: 768px) {
  .main-row {
    flex-wrap: wrap;
    padding: 0.2rem 0.5rem;
    gap: 0.5rem;
    min-height: auto;
  }

  .drag-handle {
    display: none;
  }

  .content {
    flex: 1 1 auto;
    min-width: 0;
    width: 100%;
  }

  .name-row {
    flex-wrap: wrap;
    gap: 0.4rem;
    width: 100%;
  }

  .name {
    font-size: 0.85rem;
    flex: 1 1 auto;
    min-width: 60px;
    max-width: 100%;
  }

  .meta-inline {
    flex-wrap: wrap;
    width: 100%;
    gap: 0.3rem;
    font-size: 0.7rem;
    margin-top: 0.2rem;
  }

  .tags-list {
    flex-wrap: wrap;
    gap: 0.2rem;
  }

  .badge {
    font-size: 0.6rem;
    padding: 0.05rem 0.3rem;
  }

  .actions {
    width: 100%;
    flex-wrap: wrap;
    gap: 0.2rem;
    margin-top: 0.3rem;
  }

  .btn-icon.small {
    padding: 0.25rem;
    min-width: 1.8rem;
    height: 1.8rem;
  }

  .expanded-content {
    padding: 0.5rem;
  }

  .description-view {
    font-size: 0.8rem;
  }

  .mini-input {
    padding: 0.25rem 0.5rem !important;
    font-size: 0.8rem !important;
  }

  .suggested-tags {
    gap: 0.15rem;
  }

  .tag-chip.mini {
    font-size: 0.55rem;
    padding: 0.05rem 0.3rem;
  }
}

@media (max-width: 480px) {
  .main-row {
    padding: 0.15rem 0.3rem;
    gap: 0.3rem;
  }

  .name {
    font-size: 0.8rem;
  }

  .meta-inline {
    font-size: 0.65rem;
    gap: 0.2rem;
  }

  .badge {
    font-size: 0.55rem;
    padding: 0.04rem 0.25rem;
  }

  .btn-icon.small {
    padding: 0.2rem;
    min-width: 1.6rem;
    height: 1.6rem;
  }

  .expanded-content {
    padding: 0.4rem;
  }

  .description-view {
    font-size: 0.75rem;
  }

  .mini-input {
    padding: 0.2rem 0.4rem !important;
    font-size: 0.75rem !important;
  }
}
</style>
