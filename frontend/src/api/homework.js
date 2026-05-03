import api from './client'

export const getLatestHomework = () =>
  api.get('/lessons/latest-homework').then(r => r.data)

export const uploadHomeworkImage = (lessonId, file) => {
  const form = new FormData()
  form.append('image', file)
  return api.post(`/lessons/${lessonId}/homework`, form).then(r => r.data)
}

export const deleteHomeworkImage = (id) => api.post(`/homework/${id}/delete`)

export const fetchHomeworkImageBlob = (id) =>
  api.get(`/homework/${id}/image`, { responseType: 'blob' }).then(r => URL.createObjectURL(r.data))

export const getHomeworkAllForLesson = (lessonId) =>
  api.get(`/lessons/${lessonId}/homework/all`).then(r => r.data)
