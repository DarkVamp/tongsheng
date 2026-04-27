import api from './client'

export const getLessons = () => api.get('/lessons').then(r => r.data)

export const createLesson = (data) => api.post('/lessons', data).then(r => r.data)

export const deleteLesson = (id) => api.post(`/lessons/${id}/delete`)

export const getLessonAttendance = (id) => api.get(`/lessons/${id}/attendance`).then(r => r.data)

export const setAttendance = (lessonId, studentId, present) =>
  api.post(`/lessons/${lessonId}/attendance`, { studentId, present }).then(r => r.data)

export const toggleStudent = (userId) => api.post(`/users/${userId}/toggle-student`).then(r => r.data)
