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
    'common.cancel': 'Abbrechen',

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
    'family.allRecordings': 'Alle Aufnahmen',
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

    // Tabs
    'teacher.tabRecordings': 'Aufnahmen',
    'teacher.tabStudents': 'Schüler',
    'teacher.tabLessons': 'Unterricht',

    // Familien-Verwaltung
    'teacher.createFamily': 'Familie anlegen',
    'teacher.familyNamePlaceholder': 'Familienname…',
    'teacher.deleteFamily': 'Familie löschen',
    'teacher.confirmDeleteFamily': (name) => `Familie „${name}" und alle zugehörigen Mitglieder wirklich löschen?`,
    'teacher.noMembers': 'Keine Mitglieder.',

    // Schüler-Tab
    'teacher.markStudent': 'Als Schüler markieren',
    'teacher.unmarkStudent': 'Schüler-Markierung entfernen',
    'teacher.noFamilies': 'Keine Familien vorhanden.',
    'teacher.studentBadge': 'Schüler',

    // Unterricht-Tab
    'teacher.lessonDate': 'Datum',
    'teacher.lessonTitle': 'Titel (optional)',
    'teacher.createLesson': 'Stunde anlegen',
    'teacher.noLessons': 'Noch keine Unterrichtsstunden.',
    'teacher.attendance': 'Anwesenheit',
    'teacher.present': 'Anwesend',
    'teacher.absent': 'Abwesend',
    'teacher.confirmDeleteLesson': 'Unterrichtsstunde wirklich löschen?',
    'teacher.lessonCount': (p, t) => `${p} / ${t}`,

    // Einladungen
    'teacher.inviteButton': 'Einladen',
    'teacher.inviteTitle': 'Neue Einladung',
    'teacher.inviteEmail': 'E-Mail-Adresse',
    'teacher.inviteRole': 'Rolle',
    'teacher.inviteFamily': 'Familie',
    'teacher.inviteSend': 'Einladung erstellen',
    'teacher.inviteSuccess': 'Einladungslink erstellt. Bitte teilen:',
    'teacher.inviteError': 'Einladung fehlgeschlagen.',
    'teacher.inviteExpires': (d) => `Gültig bis ${d}`,
    'teacher.copyLink': 'Link kopieren',
    'teacher.roleFamily': 'Familie',
    'teacher.roleFamilyMember': 'Familienmitglied',
    'teacher.selectFamily': '— Familie wählen —',
    'teacher.pendingInvitations': (n) => `${n} ausstehende Einladung${n === 1 ? '' : 'en'}`,
    'teacher.pendingInvitationsTitle': 'Ausstehende Einladungen',

    // Registrierung
    'register.subtitle': 'Registrierung',
    'register.email': 'E-Mail',
    'register.role': 'Rolle',
    'register.family': 'Familie',
    'register.roleFamily': 'Familie',
    'register.roleFamilyMember': 'Familienmitglied',
    'register.yourName': 'Dein Name',
    'register.namePlaceholder': 'z.B. Mama, Papa, Geschwister…',
    'register.password': 'Passwort',
    'register.passwordConfirm': 'Passwort bestätigen',
    'register.submit': 'Registrieren',
    'register.submitting': 'Registrieren…',
    'register.noToken': 'Kein Einladungslink angegeben.',
    'register.invalidToken': 'Dieser Einladungslink ist ungültig oder abgelaufen.',
    'register.nameRequired': 'Bitte Namen eingeben.',
    'register.passwordTooShort': 'Passwort muss mindestens 6 Zeichen haben.',
    'register.passwordMismatch': 'Passwörter stimmen nicht überein.',
    'register.failed': 'Registrierung fehlgeschlagen.',

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
    'common.cancel': '取消',

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
    'family.allRecordings': '所有录音',
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

    // 标签页
    'teacher.tabRecordings': '录音',
    'teacher.tabStudents': '学生',
    'teacher.tabLessons': '课程',

    // 家庭管理
    'teacher.createFamily': '创建家庭',
    'teacher.familyNamePlaceholder': '家庭名称…',
    'teacher.deleteFamily': '删除家庭',
    'teacher.confirmDeleteFamily': (name) => `确定删除家庭「${name}」及其所有成员？`,
    'teacher.noMembers': '暂无成员。',

    // 学生标签页
    'teacher.markStudent': '标为学生',
    'teacher.unmarkStudent': '取消学生标记',
    'teacher.noFamilies': '暂无家庭。',
    'teacher.studentBadge': '学生',

    // 课程标签页
    'teacher.lessonDate': '日期',
    'teacher.lessonTitle': '标题（可选）',
    'teacher.createLesson': '创建课程',
    'teacher.noLessons': '暂无课程。',
    'teacher.attendance': '出勤',
    'teacher.present': '在场',
    'teacher.absent': '缺席',
    'teacher.confirmDeleteLesson': '确定删除此课程？',
    'teacher.lessonCount': (p, t) => `${p} / ${t}`,

    // 邀请
    'teacher.inviteButton': '邀请',
    'teacher.inviteTitle': '新邀请',
    'teacher.inviteEmail': '邮箱地址',
    'teacher.inviteRole': '角色',
    'teacher.inviteFamily': '家庭',
    'teacher.inviteSend': '创建邀请',
    'teacher.inviteSuccess': '邀请链接已创建，请分享：',
    'teacher.inviteError': '邀请失败。',
    'teacher.inviteExpires': (d) => `有效期至 ${d}`,
    'teacher.copyLink': '复制链接',
    'teacher.roleFamily': '家庭',
    'teacher.roleFamilyMember': '家庭成员',
    'teacher.selectFamily': '— 选择家庭 —',
    'teacher.pendingInvitations': (n) => `${n} 个待处理邀请`,
    'teacher.pendingInvitationsTitle': '待处理邀请',

    // 注册
    'register.subtitle': '注册',
    'register.email': '邮箱',
    'register.role': '角色',
    'register.family': '家庭',
    'register.roleFamily': '家庭',
    'register.roleFamilyMember': '家庭成员',
    'register.yourName': '您的姓名',
    'register.namePlaceholder': '例如：妈妈、爸爸、兄弟姐妹…',
    'register.password': '密码',
    'register.passwordConfirm': '确认密码',
    'register.submit': '注册',
    'register.submitting': '注册中…',
    'register.noToken': '未提供邀请链接。',
    'register.invalidToken': '此邀请链接无效或已过期。',
    'register.nameRequired': '请输入姓名。',
    'register.passwordTooShort': '密码至少需要6个字符。',
    'register.passwordMismatch': '两次密码不一致。',
    'register.failed': '注册失败。',

    // Dates
    'date.locale': 'zh-CN',
  },
}

export default translations
