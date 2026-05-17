import api from './client'

export const getLatestHomework = () =>
  api.get('/lessons/latest-homework').then(r => r.data)

export const getLessonFamilyHomework = (lessonId) =>
  api.get(`/lessons/${lessonId}/family-homework`).then(r => r.data)

export const uploadHomeworkImage = (lessonId, file, hwType) => {
  const form = new FormData()
  form.append('image', file)
  form.append('homework_type', hwType)
  return api.post(`/lessons/${lessonId}/homework`, form).then(r => r.data)
}

export const deleteHomeworkImage = (id) => api.post(`/homework/${id}/delete`)

export const fetchHomeworkImageBlob = (id) =>
  api.get(`/homework/${id}/image`, { responseType: 'blob' }).then(r => URL.createObjectURL(r.data))

export const getHomeworkAllForLesson = (lessonId) =>
  api.get(`/lessons/${lessonId}/homework/all`).then(r => r.data)

export const getHomeworkByType = (lessonId) =>
  api.get(`/lessons/${lessonId}/homework/by-type`).then(r => r.data)

export const uploadHomeworkAudio = (lessonId, file, hwType) => {
  const form = new FormData()
  form.append('audio', file)
  form.append('homework_type', hwType)
  return api.post(`/lessons/${lessonId}/homework-audio`, form).then(r => r.data)
}

export const fetchHomeworkAudioBlob = (id) =>
  api.get(`/homework-audio/${id}/stream`, { responseType: 'blob' }).then(r => URL.createObjectURL(r.data))

export const deleteHomeworkAudio = (id) => api.post(`/homework-audio/${id}/delete`)
