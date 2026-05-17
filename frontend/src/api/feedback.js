import api from './client'

export const getLessonFeedback = (lessonId) =>
  api.get(`/lessons/${lessonId}/feedback`).then(r => r.data)

export const createFeedbackMessage = (lessonId, studentId, text) =>
  api.post(`/lessons/${lessonId}/feedback/${studentId}`, { text }).then(r => r.data)

export const patchFeedbackMessage = (id, text) =>
  api.patch(`/feedback/messages/${id}`, { text }).then(r => r.data)

export const uploadFeedbackAttachment = (messageId, file) => {
  const form = new FormData()
  form.append('file', file)
  return api.post(`/feedback/messages/${messageId}/attachment`, form).then(r => r.data)
}

export const fetchFeedbackAttachmentBlob = (id) =>
  api.get(`/feedback/attachments/${id}/stream`, { responseType: 'blob' }).then(r => URL.createObjectURL(r.data))

export const deleteFeedbackMessage = (id) => api.post(`/feedback/messages/${id}/delete`)

export const deleteFeedbackAttachment = (id) => api.post(`/feedback/attachments/${id}/delete`)
