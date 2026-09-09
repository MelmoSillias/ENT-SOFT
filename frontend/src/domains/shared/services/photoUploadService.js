import api from '@/services/api'

export async function uploadEntityPhoto(path, file) {
  const body = new FormData()
  body.append('file', file)
  const { data } = await api.post(path, body, {
    transformRequest: [
      (payload, headers) => {
        if (headers && typeof headers.delete === 'function') {
          headers.delete('Content-Type')
        } else if (headers) {
          delete headers['Content-Type']
        }
        return payload
      },
    ],
  })
  return data
}

export async function deleteEntityPhoto(path) {
  const { data } = await api.delete(path)
  return data
}

export function uploadEmployeePhoto(id, file) {
  return uploadEntityPhoto(`/employees/${id}/photo`, file)
}

export function deleteEmployeePhoto(id) {
  return deleteEntityPhoto(`/employees/${id}/photo`)
}

export function uploadPrestatairePhoto(id, file) {
  return uploadEntityPhoto(`/prestataires/${id}/photo`, file)
}

export function deletePrestatairePhoto(id) {
  return deleteEntityPhoto(`/prestataires/${id}/photo`)
}

export function uploadUserPhoto(id, file) {
  return uploadEntityPhoto(`/users/${id}/photo`, file)
}

export function deleteUserPhoto(id) {
  return deleteEntityPhoto(`/users/${id}/photo`)
}

export function uploadMyPhoto(file) {
  return uploadEntityPhoto('/me/photo', file)
}

export function deleteMyPhoto() {
  return deleteEntityPhoto('/me/photo')
}
