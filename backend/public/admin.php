<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>探针管理后台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
</head>

<body class="bg-gray-100 min-h-screen text-gray-800">
    <div id="app" class="pb-10">
        <!-- 头部 -->
        <header class="bg-white shadow">
            <div
                class="container mx-auto px-4 md:px-6 py-4 flex flex-col md:flex-row justify-between items-center space-y-3 md:space-y-0">
                <div class="flex items-center space-x-2">
                    <i class="ri-radar-fill text-blue-600 text-2xl"></i>
                    <h1 class="text-xl font-bold font-sans">探针管理后台</h1>
                </div>
                <div class="text-sm text-gray-500 flex items-center space-x-4">
                    <span>当前时间: {{ currentTime }}</span>
                    <a href="/" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">访问前台</a>
                </div>
            </div>
        </header>

        <!-- 主体内容 -->
        <main class="container mx-auto px-4 md:px-6 py-6 md:py-8">

            <!-- 视图切换 -->
            <div class="flex space-x-2 mb-6">
                <button @click="viewMode = 'ops'"
                    :class="['px-4 py-2 rounded-lg font-medium transition', viewMode === 'ops' ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-600 border hover:bg-gray-50']">
                    <i class="ri-line-chart-line mr-1"></i> 运营视图（真实访客）
                </button>
                <button @click="viewMode = 'dev'"
                    :class="['px-4 py-2 rounded-lg font-medium transition', viewMode === 'dev' ? 'bg-purple-600 text-white shadow' : 'bg-white text-gray-600 border hover:bg-gray-50']">
                    <i class="ri-server-line mr-1"></i> 运维视图（含性能压力）
                </button>
            </div>

            <!-- 统计卡片 -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-sm mb-1 flex items-center">
                        <i :class="viewMode === 'ops' ? 'ri-user-line' : 'ri-user-heart-line'" class="mr-1"></i>
                        {{ viewMode === 'ops' ? '真实访客（累计）' : '访问压力（累计）' }}
                    </div>
                    <div class="text-2xl font-bold" :class="viewMode === 'ops' ? '' : 'text-purple-600'">
                        {{ viewMode === 'ops' ? stats.real_total : stats.ops_total }}
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-sm mb-1 flex items-center">
                        <i :class="viewMode === 'ops' ? 'ri-today-line' : 'ri-pulse-line'" class="mr-1"></i>
                        {{ viewMode === 'ops' ? '今日真实访客' : '今日访问压力' }}
                    </div>
                    <div class="text-2xl font-bold text-blue-600">
                        {{ viewMode === 'ops' ? stats.real_today : stats.ops_today }}
                    </div>
                </div>
                <div v-if="stats.bot_total > 0 || stats.bot_today > 0" class="bg-orange-50 p-6 rounded-xl shadow-sm border border-orange-100">
                    <div class="text-orange-700 text-sm mb-1 flex items-center">
                        <i class="ri-robot-line mr-1"></i> 已剔除机器人流量
                    </div>
                    <div class="text-2xl font-bold text-orange-600">
                        {{ stats.bot_total }}
                        <span class="text-xs ml-1 font-normal text-orange-500">今日 {{ stats.bot_today }}</span>
                    </div>
                    <div v-if="viewMode === 'dev'" class="text-xs text-orange-500 mt-1">
                        运维视图统计总量已包含这些流量
                    </div>
                </div>
                <div v-if="viewMode === 'ops' && stats.bot_total > 0" class="bg-amber-50 p-6 rounded-xl shadow-sm border border-amber-100 cursor-pointer hover:bg-amber-100 transition"
                    @click="showBotExplain = true">
                    <div class="text-amber-700 text-sm mb-1 flex items-center">
                        <i class="ri-information-line mr-1"></i> 点击查看剔除明细
                    </div>
                    <div class="text-sm text-amber-800">
                        {{ botTypeLabels.search_engine }} {{ stats.bot_breakdown?.search_engine || 0 }} ·
                        {{ botTypeLabels.stress_tool }} {{ stats.bot_breakdown?.stress_tool || 0 }} ·
                        {{ botTypeLabels.malicious_refresh }} {{ stats.bot_breakdown?.malicious_refresh || 0 }}
                    </div>
                </div>
            </div>

            <!-- 剔除说明提示条 -->
            <div v-if="viewMode === 'ops' && stats.bot_today > 0"
                class="mb-6 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-xl p-4 flex flex-col md:flex-row justify-between items-start md:items-center space-y-3 md:space-y-0">
                <div class="flex items-start space-x-3">
                    <i class="ri-alert-line text-amber-600 text-2xl mt-0.5"></i>
                    <div>
                        <div class="font-semibold text-amber-800">
                            今日已从真实访客中剔除 <span class="text-orange-600">{{ stats.bot_today }}</span> 条机器人流量
                        </div>
                        <div class="text-sm text-amber-700 mt-1">
                            剔除构成：
                            <span v-if="stats.today_bot_breakdown?.search_engine">搜索引擎爬虫 {{ stats.today_bot_breakdown.search_engine }} 条；</span>
                            <span v-if="stats.today_bot_breakdown?.stress_tool">压测工具 {{ stats.today_bot_breakdown.stress_tool }} 条；</span>
                            <span v-if="stats.today_bot_breakdown?.malicious_refresh">恶意刷新/高频访问 {{ stats.today_bot_breakdown.malicious_refresh }} 条；</span>
                            <span v-if="stats.today_bot_breakdown?.manual">人工判为机器人 {{ stats.today_bot_breakdown.manual }} 条。</span>
                            <span class="text-amber-600 ml-1">性能压力仍保留在运维视图中查看。</span>
                        </div>
                    </div>
                </div>
                <button @click="showBotExplain = true"
                    class="px-3 py-1.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm">
                    <i class="ri-question-line mr-1"></i> 为什么下降？
                </button>
            </div>

            <!-- 工具栏 -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
                <div class="relative w-full md:w-96">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="ri-search-line text-gray-400"></i>
                    </span>
                    <input type="text" v-model="searchQuery" @keyup.enter="fetchData(1)"
                        class="w-full py-2 pl-10 pr-4 text-gray-700 bg-white border rounded-lg focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        placeholder="搜索 IP / 城市 / 备注...">
                </div>
                <div class="flex space-x-2">
                    <select v-model="botFilter" @change="fetchData(1)"
                        class="px-3 py-2 bg-white border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="all">全部记录</option>
                        <option value="real">仅真实访客</option>
                        <option value="bot">仅机器人/已剔除</option>
                    </select>
                    <button @click="fetchData(1)"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="ri-refresh-line mr-1"></i> 刷新
                    </button>
                </div>
            </div>

            <!-- 数据表格 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-600 text-sm tracking-wider whitespace-nowrap">
                                <th class="px-6 py-4 font-semibold">ID</th>
                                <th class="px-6 py-4 font-semibold">IP / 位置</th>
                                <th class="px-6 py-4 font-semibold">设备信息</th>
                                <th class="px-6 py-4 font-semibold">类型</th>
                                <th class="px-6 py-4 font-semibold">备注</th>
                                <th class="px-6 py-4 font-semibold">访问时间</th>
                                <th class="px-6 py-4 font-semibold text-right">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="item in visitors" :key="item.id"
                                :class="['hover:bg-gray-50 transition whitespace-nowrap', item.is_bot ? 'bg-orange-50/40' : '']">
                                <td class="px-6 py-4 text-gray-500">#{{ item.id }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ item.ip }}</div>
                                    <div class="text-xs text-gray-500">{{ item.country }} {{ item.city }}</div>
                                    <div class="text-xs text-gray-400">{{ item.isp }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <i :class="getDeviceIcon(item)"></i>
                                        {{ item.os }} / {{ item.browser }}
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        {{ item.screen_width }}x{{ item.screen_height }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span v-if="item.is_bot"
                                        :class="['inline-block px-2 py-0.5 rounded text-xs font-medium', badgeClass(item.bot_type)]">
                                        <i class="ri-robot-line mr-0.5"></i>
                                        {{ botTypeLabels[item.bot_type] || '机器人' }}
                                    </span>
                                    <span v-else
                                        class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                        <i class="ri-user-line mr-0.5"></i> 真实访客
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="item.remark"
                                        class="text-sm text-gray-800 bg-yellow-50 px-2 py-1 rounded inline-block">
                                        {{ item.remark }}
                                    </div>
                                    <div v-else class="text-sm text-gray-300 italic">无</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ item.created_at }}
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button @click="openDetail(item)"
                                        class="text-blue-600 hover:text-blue-800 text-sm">详情</button>
                                    <button @click="editRemark(item)"
                                        class="text-gray-600 hover:text-gray-800 text-sm">备注</button>
                                    <button @click="openMarkBot(item)"
                                        :class="['text-sm', item.is_bot ? 'text-green-600 hover:text-green-800' : 'text-orange-600 hover:text-orange-800']">
                                        {{ item.is_bot ? '改判为人' : '判为机器人' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="visitors.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                    暂无数据
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 分页 -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        共 {{ total }} 条记录，第 {{ page }} / {{ totalPages }} 页
                    </div>
                    <div class="flex space-x-2">
                        <button @click="prevPage" :disabled="page <= 1"
                            class="px-3 py-1 bg-white border rounded hover:bg-gray-100 disabled:opacity-50">上一页</button>
                        <button @click="nextPage" :disabled="page >= totalPages"
                            class="px-3 py-1 bg-white border rounded hover:bg-gray-100 disabled:opacity-50">下一页</button>
                    </div>
                </div>
            </div>

        </main>

        <!-- 详情弹窗 -->
        <div v-if="showDetailModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="showDetailModal = false">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50 flex-shrink-0">
                    <div class="flex items-center space-x-3">
                        <h3 class="text-lg font-bold">访客详情 #{{ currentItem.id }}</h3>
                        <span v-if="currentItem.is_bot" :class="['px-2 py-0.5 rounded text-xs font-medium', badgeClass(currentItem.bot_type)]">
                            <i class="ri-robot-line mr-0.5"></i>
                            {{ botTypeLabels[currentItem.bot_type] || '机器人' }}
                        </span>
                        <span v-else class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                            <i class="ri-user-line mr-0.5"></i> 真实访客
                        </span>
                    </div>
                    <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600"><i
                            class="ri-close-line text-xl"></i></button>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <div v-if="currentItem.is_bot" class="px-6 py-4 bg-orange-50 border-b border-orange-100">
                        <div class="font-semibold text-orange-800 mb-2 flex items-center">
                            <i class="ri-shield-user-line mr-1"></i> 机器人剔除判定信息
                        </div>
                        <div class="text-sm text-orange-700 space-y-1">
                            <div>判定类型：<b>{{ botTypeLabels[currentItem.bot_type] || currentItem.bot_type }}</b></div>
                            <div>判定原因：{{ currentItem.bot_reason || '-' }}</div>
                            <div v-if="currentItem.bot_evidence">判定证据：<code class="bg-orange-100 px-1 rounded text-xs break-all">{{ formatEvidence(currentItem.bot_evidence) }}</code></div>
                            <div>判定方：{{ currentItem.bot_verified_by === 'system_auto' ? '系统自动识别' : (currentItem.bot_verified_by || '未知') }}</div>
                            <div>判定时间：{{ currentItem.bot_verified_at || '-' }}</div>
                        </div>
                        <div class="mt-3">
                            <button @click="openMarkBot(currentItem)"
                                class="px-3 py-1.5 bg-white text-orange-700 border border-orange-300 rounded hover:bg-orange-100 text-sm">
                                <i class="ri-edit-line mr-1"></i> 人工改判
                            </button>
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div v-for="field in detailFields" :key="field.key" class="border-b border-gray-100 py-2">
                            <span class="text-gray-500 block mb-1 text-xs">{{ field.label }}</span>
                            <span class="font-mono text-gray-800 break-all">{{ formatValue(currentItem[field.key])
                                }}</span>
                        </div>
                    </div>

                    <div v-if="currentItem.audit_logs && currentItem.audit_logs.length > 0" class="px-6 py-4 bg-blue-50 border-t border-blue-100">
                        <div class="font-semibold text-blue-800 mb-2 flex items-center">
                            <i class="ri-file-list-3-line mr-1"></i> 人工改判记录（证据留痕）
                        </div>
                        <div class="space-y-2">
                            <div v-for="log in currentItem.audit_logs" :key="log.id"
                                class="bg-white border border-blue-100 rounded p-3 text-sm">
                                <div class="flex justify-between items-center mb-1">
                                    <div class="font-medium text-blue-800">
                                        操作人：{{ log.operator || 'admin' }}
                                    </div>
                                    <div class="text-xs text-gray-500">{{ log.created_at }}</div>
                                </div>
                                <div class="text-xs text-gray-600 mb-1">
                                    变更：
                                    <span :class="log.old_is_bot ? 'text-orange-600' : 'text-green-600'">{{ log.old_is_bot ? ('机器人(' + (botTypeLabels[log.old_bot_type] || log.old_bot_type) + ')') : '真实访客' }}</span>
                                    <i class="ri-arrow-right-line mx-1"></i>
                                    <span :class="log.new_is_bot ? 'text-orange-600' : 'text-green-600'">{{ log.new_is_bot ? ('机器人(' + (botTypeLabels[log.new_bot_type] || log.new_bot_type) + ')') : '真实访客' }}</span>
                                </div>
                                <div v-if="log.reason" class="text-xs text-gray-700">改判理由：{{ log.reason }}</div>
                                <div v-if="log.evidence" class="text-xs text-gray-700 mt-1">
                                    留痕证据：<code class="bg-blue-50 px-1 rounded break-all">{{ formatEvidence(log.evidence) }}</code>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-amber-50 border-t border-amber-100 text-xs text-amber-700 space-y-1">
                        <div class="font-semibold mb-2"><i class="ri-information-line mr-1"></i>数据准确性说明</div>
                        <div>• <b>系统版本</b>：Chrome 90+ 将 macOS 版本冻结为 10.15.7，无法获取真实版本</div>
                        <div>• <b>设备内存</b>：浏览器返回模糊值（如 4/8GB），非精确值</div>
                        <div>• <b>网络类型</b>：仅显示等效网速，无法检测 WiFi/有线/代理</div>
                        <div>• <b>IP 定位</b>：本地/内网 IP 无法定位，需部署到公网服务器</div>
                        <div class="text-amber-600 mt-2">以上为浏览器隐私保护机制限制，非采集错误。</div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t bg-gray-50 text-right flex-shrink-0">
                    <button @click="showDetailModal = false"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">关闭</button>
                </div>
            </div>
        </div>

        <!-- 备注弹窗 -->
        <div v-if="showRemarkModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="showRemarkModal = false">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-bold">编辑备注</h3>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">姓名 / 备注信息</label>
                        <textarea v-model="remarkForm.remark" rows="3"
                            class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 border-t bg-gray-50 flex justify-end space-x-3">
                    <button @click="showRemarkModal = false"
                        class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">取消</button>
                    <button @click="saveRemark"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">保存</button>
                </div>
            </div>
        </div>

        <!-- 人工改判弹窗 -->
        <div v-if="showMarkBotModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="showMarkBotModal = false">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="px-6 py-4 border-b flex justify-between items-center">
                    <h3 class="text-lg font-bold">
                        {{ markBotForm.is_bot ? '判为机器人' : '改判为真实访客' }} #{{ markBotForm.id }}
                    </h3>
                    <button @click="showMarkBotModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4 text-sm">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">判定结果</label>
                        <div class="flex space-x-2">
                            <label class="flex items-center space-x-2 px-3 py-2 border rounded cursor-pointer"
                                :class="markBotForm.is_bot ? 'border-orange-400 bg-orange-50' : ''">
                                <input type="radio" v-model="markBotForm.is_bot" :value="1" />
                                <span class="text-orange-700"><i class="ri-robot-line mr-1"></i> 机器人（从真实访客剔除）</span>
                            </label>
                            <label class="flex items-center space-x-2 px-3 py-2 border rounded cursor-pointer"
                                :class="!markBotForm.is_bot ? 'border-green-400 bg-green-50' : ''">
                                <input type="radio" v-model="markBotForm.is_bot" :value="0" />
                                <span class="text-green-700"><i class="ri-user-line mr-1"></i> 真实访客</span>
                            </label>
                        </div>
                    </div>
                    <div v-if="markBotForm.is_bot">
                        <label class="block text-sm font-medium text-gray-700 mb-2">机器人类型</label>
                        <select v-model="markBotForm.bot_type"
                            class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-blue-500">
                            <option value="search_engine">搜索引擎爬虫</option>
                            <option value="stress_tool">压测工具</option>
                            <option value="malicious_refresh">恶意刷新 / 高频访问</option>
                            <option value="manual">人工判定的其他机器人</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            改判理由 <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="markBotForm.reason" rows="3" placeholder="请说明改判理由，将作为审计证据留存..."
                            class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-blue-500"></textarea>
                        <div class="text-xs text-gray-500 mt-1">
                            <i class="ri-shield-check-line mr-1"></i>
                            本操作将记录操作人、时间、变更前后状态与理由，用于审计追溯。
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            证据留痕 <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="markBotForm.evidence_text" rows="2" placeholder="必填：粘贴 UA、日志、工单号、截图说明等判定依据..."
                            class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-blue-500"></textarea>
                        <div class="text-xs text-gray-500 mt-1">
                            <i class="ri-shield-check-line mr-1"></i>
                            系统会自动合并原始识别证据，与人工证据一并写入审计日志。
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">操作人</label>
                        <input v-model="markBotForm.operator"
                            class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-blue-500" />
                    </div>
                </div>
                <div class="px-6 py-4 border-t bg-gray-50 flex justify-end space-x-3">
                    <button @click="showMarkBotModal = false"
                        class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">取消</button>
                    <button @click="submitMarkBot"
                        :class="['px-4 py-2 text-white rounded', markBotForm.is_bot ? 'bg-orange-600 hover:bg-orange-700' : 'bg-green-600 hover:bg-green-700']"
                        :disabled="!markBotForm.reason.trim() || !markBotForm.evidence_text.trim()">
                        <i class="ri-save-line mr-1"></i> 提交并留痕
                    </button>
                </div>
            </div>
        </div>

        <!-- 剔除说明/下降原因弹窗 -->
        <div v-if="showBotExplain" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="showBotExplain = false">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col">
                <div class="px-6 py-4 border-b flex justify-between items-center bg-gradient-to-r from-amber-50 to-orange-50">
                    <div>
                        <h3 class="text-lg font-bold text-amber-800"><i class="ri-robot-line mr-1"></i> 机器人流量剔除说明</h3>
                        <div class="text-xs text-amber-600 mt-0.5">为什么运营视图比运维视图少？</div>
                    </div>
                    <button @click="showBotExplain = false" class="text-gray-500 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4 text-sm overflow-y-auto flex-1">
                    <div class="bg-blue-50 border border-blue-100 rounded p-4">
                        <div class="font-semibold text-blue-800 mb-2">📊 双视图机制</div>
                        <ul class="space-y-1 text-blue-700 text-xs list-disc list-inside">
                            <li><b>运营视图</b>：仅统计真实访客转化，机器人流量已剔除，用于业务评估。</li>
                            <li><b>运维视图</b>：统计全部访问压力（含机器人），用于服务器资源评估与容量规划。</li>
                        </ul>
                    </div>

                    <div class="bg-gray-50 border border-gray-100 rounded p-4">
                        <div class="font-semibold text-gray-800 mb-3">🤖 已剔除机器人类型（累计 {{ stats.bot_total }} / 今日 {{ stats.bot_today }}）</div>
                        <div class="space-y-3">
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="ri-search-line"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">
                                        {{ botTypeLabels.search_engine }}
                                        <span class="text-xs text-gray-500 ml-2">累计 {{ stats.bot_breakdown?.search_engine || 0 }} · 今日 {{ stats.today_bot_breakdown?.search_engine || 0 }}</span>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-0.5">
                                        识别 Googlebot、Baiduspider、Bingbot、Yandexbot 等主流搜索引擎爬虫。这些访问并非真实潜在用户，从运营转化中剔除。
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-purple-100 text-purple-700 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="ri-dashboard-line"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">
                                        {{ botTypeLabels.stress_tool }}
                                        <span class="text-xs text-gray-500 ml-2">累计 {{ stats.bot_breakdown?.stress_tool || 0 }} · 今日 {{ stats.today_bot_breakdown?.stress_tool || 0 }}</span>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-0.5">
                                        识别 ApacheBench、wrk、JMeter、Python-requests、curl 脚本等压测工具特征。流量仍计入运维视图用于评估系统抗压能力。
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-red-100 text-red-700 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="ri-refresh-line"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">
                                        {{ botTypeLabels.malicious_refresh }}
                                        <span class="text-xs text-gray-500 ml-2">累计 {{ stats.bot_breakdown?.malicious_refresh || 0 }} · 今日 {{ stats.today_bot_breakdown?.malicious_refresh || 0 }}</span>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-0.5">
                                        1 分钟内同 IP ≥ 10 次请求，或 User-Agent 异常（空/过短）。判定为恶意刷量或爬虫脚本，予以剔除。
                                    </div>
                                </div>
                            </div>
                            <div v-if="(stats.bot_breakdown?.manual || 0) > 0" class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-orange-100 text-orange-700 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="ri-user-settings-line"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">
                                        人工判定机器人
                                        <span class="text-xs text-gray-500 ml-2">累计 {{ stats.bot_breakdown?.manual || 0 }} · 今日 {{ stats.today_bot_breakdown?.manual || 0 }}</span>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-0.5">
                                        由管理员人工标记并记录证据的可疑访问，所有改判均有审计日志可追溯。
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-100 rounded p-4">
                        <div class="font-semibold text-amber-800 mb-2">🔍 人工改判机制</div>
                        <div class="text-xs text-amber-700 space-y-1">
                            <div>• 管理员可在访客列表或详情页对任一记录进行"判为机器人 / 改判为人"操作。</div>
                            <div>• 改判时必须填写理由，并可选填证据，系统会记录操作人、时间、变更前后状态。</div>
                            <div>• 所有改判操作将写入审计日志，支持完整追溯。</div>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t bg-gray-50 text-right">
                    <button @click="showBotExplain = false"
                        class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-700">知道了</button>
                </div>
            </div>
        </div>

    </div>

    <script>
        const { createApp, ref, onMounted, reactive, computed } = Vue;

        createApp({
            setup() {
                const visitors = ref([]);
                const total = ref(0);
                const page = ref(1);
                const totalPages = ref(1);
                const searchQuery = ref('');
                const botFilter = ref('all');
                const viewMode = ref('ops');
                const stats = ref({
                    total: 0, today: 0,
                    ops_total: 0, ops_today: 0,
                    real_total: 0, real_today: 0,
                    bot_total: 0, bot_today: 0,
                    bot_breakdown: { search_engine: 0, stress_tool: 0, malicious_refresh: 0, manual: 0 },
                    today_bot_breakdown: { search_engine: 0, stress_tool: 0, malicious_refresh: 0, manual: 0 },
                });

                const showDetailModal = ref(false);
                const showRemarkModal = ref(false);
                const showMarkBotModal = ref(false);
                const showBotExplain = ref(false);
                const currentItem = ref({});
                const remarkForm = ref({ id: null, remark: '' });
                const markBotForm = reactive({
                    id: null, is_bot: 1, bot_type: 'search_engine',
                    reason: '', evidence_text: '', operator: 'admin',
                });
                const currentTime = ref('');

                const botTypeLabels = {
                    search_engine: '搜索引擎爬虫',
                    stress_tool: '压测工具',
                    malicious_refresh: '恶意刷新/高频',
                    manual: '人工判定机器人',
                };

                const badgeClass = (type) => {
                    switch (type) {
                        case 'search_engine': return 'bg-blue-100 text-blue-700';
                        case 'stress_tool': return 'bg-purple-100 text-purple-700';
                        case 'malicious_refresh': return 'bg-red-100 text-red-700';
                        case 'manual': return 'bg-orange-100 text-orange-700';
                        default: return 'bg-gray-100 text-gray-700';
                    }
                };

                const detailFields = [
                    { key: 'id', label: 'ID' },
                    { key: 'ip', label: 'IP 地址' },
                    { key: 'country', label: '国家' },
                    { key: 'city', label: '城市' },
                    { key: 'isp', label: '运营商' },
                    { key: 'user_agent', label: '用户代理' },
                    { key: 'browser', label: '浏览器' },
                    { key: 'browser_version', label: '浏览器版本' },
                    { key: 'os', label: '操作系统' },
                    { key: 'os_version', label: '系统版本' },
                    { key: 'device_type', label: '设备类型' },
                    { key: 'screen_width', label: '屏幕宽度' },
                    { key: 'screen_height', label: '屏幕高度' },
                    { key: 'window_width', label: '窗口宽度' },
                    { key: 'window_height', label: '窗口高度' },
                    { key: 'language', label: '语言偏好' },
                    { key: 'timezone', label: '时区' },
                    { key: 'platform', label: '平台' },
                    { key: 'cookie_enabled', label: 'Cookie 状态' },
                    { key: 'touch_points', label: '触控点数' },
                    { key: 'device_memory', label: '设备内存 (GB)' },
                    { key: 'cpu_cores', label: 'CPU 核心数' },
                    { key: 'connection_type', label: '网络类型' },
                    { key: 'referrer', label: '来源页面' },
                    { key: 'remark', label: '备注' },
                    { key: 'created_at', label: '访问时间' }
                ];

                const fetchStats = async () => {
                    try {
                        const res = await fetch('/api.php?action=stats');
                        const json = await res.json();
                        if (json.status === 'success') {
                            stats.value = json;
                        }
                    } catch (e) {
                        console.error(e);
                    }
                };

                const fetchData = async (p = 1) => {
                    try {
                        let url = `/api.php?action=list&page=${p}&search=${searchQuery.value}`;
                        if (botFilter.value !== 'all') {
                            url += `&bot_filter=${botFilter.value}`;
                        }
                        const res = await fetch(url);
                        const json = await res.json();
                        if (json.status === 'success') {
                            visitors.value = json.data;
                            total.value = json.total;
                            page.value = json.page;
                            totalPages.value = json.pages;
                        }
                    } catch (e) {
                        console.error(e);
                    }
                    fetchStats();
                };

                const prevPage = () => {
                    if (page.value > 1) fetchData(page.value - 1);
                };

                const nextPage = () => {
                    if (page.value < totalPages.value) fetchData(page.value + 1);
                };

                const openDetail = async (item) => {
                    try {
                        const res = await fetch(`/api.php?action=detail&id=${item.id}`);
                        const json = await res.json();
                        if (json.status === 'success') {
                            currentItem.value = json.data;
                            showDetailModal.value = true;
                        }
                    } catch (e) {
                        currentItem.value = item;
                        showDetailModal.value = true;
                    }
                };

                const editRemark = (item) => {
                    remarkForm.value = { id: item.id, remark: item.remark || '' };
                    showRemarkModal.value = true;
                };

                const saveRemark = async () => {
                    try {
                        const res = await fetch('/api.php?action=remark', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(remarkForm.value)
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            showRemarkModal.value = false;
                            fetchData(page.value);
                        } else {
                            alert('保存失败');
                        }
                    } catch (e) {
                        alert('错误: ' + e.message);
                    }
                };

                const openMarkBot = (item) => {
                    markBotForm.id = item.id;
                    markBotForm.is_bot = item.is_bot ? 1 : 0;
                    markBotForm.bot_type = item.bot_type || 'search_engine';
                    markBotForm.reason = '';
                    markBotForm.evidence_text = '';
                    showMarkBotModal.value = true;
                };

                const submitMarkBot = async () => {
                    if (!markBotForm.reason.trim()) {
                        alert('请填写改判理由');
                        return;
                    }
                    if (!markBotForm.evidence_text.trim()) {
                        alert('请填写证据留痕（必填）');
                        return;
                    }
                    try {
                        const payload = {
                            id: markBotForm.id,
                            is_bot: markBotForm.is_bot ? 1 : 0,
                            bot_type: markBotForm.is_bot ? markBotForm.bot_type : '',
                            reason: markBotForm.reason,
                            evidence: markBotForm.evidence_text,
                            operator: markBotForm.operator || 'admin',
                        };
                        const res = await fetch('/api.php?action=mark_bot', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload),
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            showMarkBotModal.value = false;
                            fetchData(page.value);
                        } else {
                            alert('操作失败：' + (json.message || '未知错误'));
                        }
                    } catch (e) {
                        alert('错误: ' + e.message);
                    }
                };

                const getDeviceIcon = (item) => {
                    const os = (item.os || '').toLowerCase();
                    if (os.includes('mac') || os.includes('windows') || os.includes('linux')) return 'ri-computer-line';
                    if (os.includes('android') || os.includes('ios') || os.includes('ipad')) return 'ri-smartphone-line';
                    return 'ri-device-line';
                };

                const formatValue = (val, key) => {
                    if (val === null || val === undefined || val === '') return '-';
                    if (val === 0) return '0';
                    if (key === 'cookie_enabled') return val ? '已启用' : '未启用';
                    return val;
                };

                const formatEvidence = (val) => {
                    if (!val) return '-';
                    if (typeof val === 'object') return JSON.stringify(val);
                    try {
                        const parsed = JSON.parse(val);
                        return JSON.stringify(parsed);
                    } catch (e) {
                        return String(val);
                    }
                };

                setInterval(() => {
                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const seconds = String(now.getSeconds()).padStart(2, '0');
                    currentTime.value = `${hours}:${minutes}:${seconds}`;
                }, 1000);

                onMounted(() => {
                    fetchData();
                    fetchStats();
                });

                return {
                    visitors, total, page, totalPages, searchQuery, botFilter, viewMode, stats,
                    showDetailModal, showRemarkModal, showMarkBotModal, showBotExplain,
                    currentItem, remarkForm, markBotForm, currentTime,
                    detailFields, botTypeLabels, badgeClass,
                    formatValue, formatEvidence, getDeviceIcon,
                    fetchData, prevPage, nextPage, openDetail, editRemark, saveRemark,
                    openMarkBot, submitMarkBot,
                };
            }
        }).mount('#app');
    </script>
</body>

</html>
