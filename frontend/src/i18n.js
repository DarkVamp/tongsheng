const translations = {
  de: {
    // Login
    'login.subtitle': 'Sprachaufnahmen',
    'login.email': 'E-Mail',
    'login.password': 'Passwort',
    'login.submit': 'Anmelden',
    'login.submitting': 'Anmelden…',
    'login.error': 'E-Mail oder Passwort falsch.',

    // Common
    'common.logout': 'Abmelden',
    'common.delete': 'Löschen',
    'common.loading': 'Lade…',
    'common.send': 'Senden',
    'common.sending': 'Speichern…',
    'common.confirmDelete': 'Aufnahme wirklich löschen?',

    // Family Dashboard
    'family.todayRecording': 'Heutige Aufnahme',
    'family.alreadyUploaded': 'Heute wurde bereits eine Aufnahme hochgeladen.',
    'family.record': 'Aufnehmen',
    'family.stopUpload': 'Stopp & Hochladen',
    'family.or': 'oder',
    'family.chooseFile': 'Datei wählen',
    'family.uploading': 'Wird hochgeladen…',
    'family.myRecordings': 'Meine Aufnahmen',
    'family.noRecordings': 'Noch keine Aufnahmen vorhanden.',
    'family.comments': (n) => n === 1 ? '1 Kommentar' : `${n} Kommentare`,
    'family.micDenied': 'Mikrofon-Zugriff verweigert.',
    'family.uploadFailed': 'Upload fehlgeschlagen.',
    'family.deleteFailed': 'Löschen fehlgeschlagen.',

    // Teacher Dashboard
    'teacher.badge': 'Lehrerin',
    'teacher.filterPlaceholder': 'Familie suchen…',
    'teacher.recordingCount': (n) => `${n} Aufnahmen`,
    'teacher.open': 'Öffnen',
    'teacher.openWithComments': (n) => `Öffnen (${n})`,
    'teacher.close': 'Zuklappen',
    'teacher.comments': 'Kommentare',
    'teacher.noComments': 'Noch kein Kommentar.',
    'teacher.commentPlaceholder': 'Kommentar schreiben…',
    'teacher.noRecordings': 'Keine Aufnahmen gefunden.',
    'teacher.deleteFailed': 'Löschen fehlgeschlagen.',

    // Dates
    'date.locale': 'de-DE',
  },

  zh: {
    // Login
    'login.subtitle': '语音练习',
    'login.email': '邮箱',
    'login.password': '密码',
    'login.submit': '登录',
    'login.submitting': '登录中…',
    'login.error': '邮箱或密码错误。',

    // Common
    'common.logout': '退出',
    'common.delete': '删除',
    'common.loading': '加载中…',
    'common.send': '发送',
    'common.sending': '发送中…',
    'common.confirmDelete': '确定要删除这条录音吗？',

    // Family Dashboard
    'family.todayRecording': '今日录音',
    'family.alreadyUploaded': '今天已上传录音。',
    'family.record': '开始录音',
    'family.stopUpload': '停止并上传',
    'family.or': '或',
    'family.chooseFile': '选择文件',
    'family.uploading': '上传中…',
    'family.myRecordings': '我的录音',
    'family.noRecordings': '暂无录音。',
    'family.comments': (n) => `${n} 条评论`,
    'family.micDenied': '麦克风访问被拒绝。',
    'family.uploadFailed': '上传失败。',
    'family.deleteFailed': '删除失败。',

    // Teacher Dashboard
    'teacher.badge': '老师',
    'teacher.filterPlaceholder': '搜索家庭…',
    'teacher.recordingCount': (n) => `${n} 条录音`,
    'teacher.open': '展开',
    'teacher.openWithComments': (n) => `展开 (${n})`,
    'teacher.close': '收起',
    'teacher.comments': '评论',
    'teacher.noComments': '暂无评论。',
    'teacher.commentPlaceholder': '写评论…',
    'teacher.noRecordings': '未找到录音。',
    'teacher.deleteFailed': '删除失败。',

    // Dates
    'date.locale': 'zh-CN',
  },
}

export default translations
