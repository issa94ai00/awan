<template>
    <div class="system-documentation" ref="docContainerRef" dir="rtl">
        <!-- Documentation Hero Banner -->
        <header class="doc-hero">
            <div class="doc-hero-content">
                <div class="doc-hero-badges">
                    <span class="system-badge">AWAN ERP · المركز المعرفي والتشغيلي</span>
                    <span class="version-badge">الإصدار الشامل 2026 · v2.4</span>
                    <span class="reading-time-badge"><el-icon><Timer /></el-icon> دليل عملي متكامل</span>
                </div>
                <h1 class="doc-hero-title">
                    <el-icon class="hero-icon"><Reading /></el-icon>
                    دليل توثيق وظائف النظام الشامل
                </h1>
                <p class="doc-hero-desc">
                    المرجع التشغيلي، التقني، والمحاسبي المعتمد لجميع وحدات ومسارات العمل في نظام <strong>أوان التقدم للمواد الصحية</strong>. يوثق قواعد المخزون، دورة حياة الفوترة والمشتريات، معادلات التكلفة، ودليل الصلاحيات وقيود اليومية.
                </p>

                <!-- Search & Filters Toolbar -->
                <div class="doc-toolbar">
                    <div class="search-box">
                        <el-input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            placeholder="ابحث عن وظيفة، شاشة، قيد محاسبي، قاعدة مخزون، كود، أو مسار عمل... (اضغط / للتركيز)"
                            clearable
                            size="large"
                            :prefix-icon="Search"
                        >
                            <template #suffix>
                                <span v-if="searchQuery.trim()" class="results-badge">
                                    {{ filteredCount }} نتيجة مطابقة
                                </span>
                                <kbd v-else class="search-shortcut" title="اضغط مفتاح / للبحث السريع">/</kbd>
                            </template>
                        </el-input>
                    </div>

                    <div class="actions-group">
                        <el-button-group>
                            <el-button
                                :type="allExpanded ? 'primary' : 'default'"
                                :icon="allExpanded ? Fold : Expand"
                                @click="toggleAllSections"
                            >
                                {{ allExpanded ? 'طي الكل' : 'توسيع الكل' }}
                            </el-button>
                            <el-button type="default" :icon="Printer" @click="printGuide">
                                طباعة الدليل / PDF
                            </el-button>
                        </el-button-group>
                    </div>
                </div>

                <!-- Quick Filter Tags / Keywords -->
                <div class="quick-tags-row">
                    <span class="quick-tags-lbl">الكلمات الأكثر بحثاً:</span>
                    <div class="quick-tags-list">
                        <button
                            v-for="tag in popularSearchTags"
                            :key="tag"
                            type="button"
                            class="quick-tag-chip"
                            :class="{ active: searchQuery.toLowerCase() === tag.toLowerCase() }"
                            @click="applySearchTag(tag)"
                        >
                            #{{ tag }}
                        </button>
                    </div>
                </div>

                <!-- Role Filter Pills Bar -->
                <div class="role-pills-bar">
                    <span class="role-filter-label">عرض مخصص حسب الدور الوظيفي:</span>
                    <div class="role-pills">
                        <button
                            v-for="role in rolesList"
                            :key="role.id"
                            type="button"
                            class="role-pill"
                            :class="{ active: selectedRole === role.id }"
                            @click="selectedRole = role.id"
                        >
                            <el-icon><component :is="role.icon" /></el-icon>
                            <span>{{ role.label }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main View Mode Tabs -->
        <div class="view-tabs-container">
            <el-tabs v-model="activeViewTab" class="doc-view-tabs">
                <el-tab-pane name="modules">
                    <template #label>
                        <span class="view-tab-label">
                            <el-icon><Collection /></el-icon>
                            <span>دليل الوحدات والوظائف</span>
                            <span class="tab-badge">{{ filteredSections.length }}</span>
                        </span>
                    </template>
                </el-tab-pane>

                <el-tab-pane name="diagrams">
                    <template #label>
                        <span class="view-tab-label">
                            <el-icon><Operation /></el-icon>
                            <span>مخططات تدفق العمليات (Flow Diagrams)</span>
                            <span class="tab-badge special">6 مخططات</span>
                        </span>
                    </template>
                </el-tab-pane>

                <el-tab-pane name="simulators">
                    <template #label>
                        <span class="view-tab-label">
                            <el-icon><Cpu /></el-icon>
                            <span>المحاكيات والحاسبات التفاعلية</span>
                            <span class="tab-badge special">4 حاسبات</span>
                        </span>
                    </template>
                </el-tab-pane>

                <el-tab-pane name="rules">
                    <template #label>
                        <span class="view-tab-label">
                            <el-icon><WarningFilled /></el-icon>
                            <span>مصفوفة القواعد والمعادلات</span>
                            <span class="tab-badge">{{ rulesCount }} قاعدة</span>
                        </span>
                    </template>
                </el-tab-pane>

                <el-tab-pane name="shortcuts">
                    <template #label>
                        <span class="view-tab-label">
                            <el-icon><Tickets /></el-icon>
                            <span>اختصارات الإنتاجية ولوحة المفاتيح</span>
                        </span>
                    </template>
                </el-tab-pane>

                <el-tab-pane name="glossary_faq">
                    <template #label>
                        <span class="view-tab-label">
                            <el-icon><QuestionFilled /></el-icon>
                            <span>المصطلحات والأسئلة الشائعة</span>
                        </span>
                    </template>
                </el-tab-pane>
            </el-tabs>
        </div>

        <!-- TAB 1: MODULES & USER GUIDES -->
        <div v-show="activeViewTab === 'modules'" class="doc-body-wrapper">
            <!-- Sticky Quick Navigation Index -->
            <aside class="doc-sidebar-nav">
                <div class="nav-card">
                    <div class="nav-header">
                        <el-icon><Menu /></el-icon>
                        <span>فهرس الوحدات ({{ filteredSections.length }})</span>
                    </div>
                    <ul class="nav-links-list">
                        <li>
                            <a
                                href="#system-architecture"
                                class="nav-item-link"
                                :class="{ active: activeSectionId === 'system-architecture' }"
                                @click.prevent="scrollToSection('system-architecture')"
                            >
                                <span class="nav-icon-badge" style="background-color: #1e3a8a18; color: #1e3a8a;">
                                    <el-icon><Cpu /></el-icon>
                                </span>
                                <span class="nav-text">المعمارية وتدفق العمليات</span>
                            </a>
                        </li>
                        <li v-for="section in filteredSections" :key="section.id">
                            <a
                                :href="`#${section.id}`"
                                class="nav-item-link"
                                :class="{ active: activeSectionId === section.id }"
                                @click.prevent="scrollToSection(section.id)"
                            >
                                <span class="nav-icon-badge" :style="{ backgroundColor: section.color + '18', color: section.color }">
                                    <el-icon><component :is="section.icon" /></el-icon>
                                </span>
                                <span class="nav-text">{{ section.shortTitle || section.title }}</span>
                                <span class="nav-badge">{{ section.items.length }}</span>
                            </a>
                        </li>
                    </ul>

                    <div class="quick-stats-box">
                        <div class="stat-item">
                            <span class="stat-num">{{ modulesCount }}</span>
                            <span class="stat-lbl">وحدة رئيسية</span>
                        </div>
                        <div class="stat-sep"></div>
                        <div class="stat-item">
                            <span class="stat-num">{{ screensCount }}</span>
                            <span class="stat-lbl">شاشة مسجلة</span>
                        </div>
                        <div class="stat-sep"></div>
                        <div class="stat-item">
                            <span class="stat-num">{{ rulesCount }}</span>
                            <span class="stat-lbl">قاعدة تشغيلية</span>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Modules Content -->
            <main class="doc-main-content">
                <!-- System Architecture & Master Workflow Blueprint -->
                <section id="system-architecture" class="doc-section-card master-blueprint-card">
                    <div class="blueprint-header">
                        <div class="section-icon-wrap" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white;">
                            <el-icon :size="24"><Cpu /></el-icon>
                        </div>
                        <div class="blueprint-title-wrap">
                            <div class="section-anchor-row">
                                <span class="blueprint-tag">البنية العامة والمبادئ الأساسية</span>
                                <button type="button" class="copy-anchor-btn" @click="copyAnchor('system-architecture')" title="نسخ رابط القسم">
                                    <el-icon><Link /></el-icon>
                                </button>
                            </div>
                            <h2 class="section-title">المعمارية التقنية وتدفق العمليات المالي والمخزني</h2>
                        </div>
                    </div>
                    <div class="blueprint-body">
                        <p class="blueprint-desc">
                            يعتمد نظام <strong>أوان التقدم (AWAN ERP Suite)</strong> على معمارية معاصرة تجمع بين سرعة الاستجابة اللحظية وموثوقية المعاملات المالية المزدوجة والتتبع الدقيق للمخزون متعدد الفروع:
                        </p>

                        <div class="blueprint-grid">
                            <div class="blueprint-box">
                                <div class="bp-icon-title">
                                    <el-icon><Connection /></el-icon>
                                    <h4>معمارية الربط والأمان</h4>
                                </div>
                                <ul>
                                    <li>خادم <strong>Laravel 12 API</strong> مؤمّن بالكامل عبر <strong>Sanctum Bearer Tokens</strong> مع فرض جلسة نشطة واحدة لكل مستخدم.</li>
                                    <li>واجهة إدارية <strong>Vue 3 SPA</strong> أحادية الصفحة مبنية على <strong>Pinia</strong> و<strong>Vue Router</strong> و<strong>Element Plus</strong>.</li>
                                    <li>دعم لغوي مزدوج (عربي وإنجليزي) مع تبديل ديناميكي لاتجاه الصفحة (RTL / LTR) ومواءمة شاشات الهواتف والأجهزة اللوحية.</li>
                                </ul>
                            </div>

                            <div class="blueprint-box">
                                <div class="bp-icon-title">
                                    <el-icon><Coin /></el-icon>
                                    <h4>المعادلة المحاسبية المركزية</h4>
                                </div>
                                <ul>
                                    <li>نظام قيد مزدوج صارم يضمن توازن: <code>الأصول = الخصوم + حقوق الملكية</code> في كل قيد يومية.</li>
                                    <li>توليد آلي لقيود اليومية عند: ترحيل الفواتير، استلام المشتريات، الدفعات، إشعارات الدائن، واهتلاكات الأصول.</li>
                                    <li>نظام عملات متعدد يدعم أسعار صرف تاريخية لحساب فروقات العملة المحققة (Realized Forex Gains/Losses).</li>
                                </ul>
                            </div>

                            <div class="blueprint-box">
                                <div class="bp-icon-title">
                                    <el-icon><Box /></el-icon>
                                    <h4>قاعدة الدِلاء الثلاثة للمخزون</h4>
                                </div>
                                <ul>
                                    <li>المخزون الكلي على الرف = <code>السليم (Available) + التالف (Damaged) + المحجور (Quarantined)</code>.</li>
                                    <li>المخزون القابل للبيع الفعلي = <code>max(0, السليم - المحجوز)</code>.</li>
                                    <li>طريقة <strong>الوارد أولاً يصرف أولاً (FIFO)</strong> الدقيقة في احتساب تكلفة البضاعة المباعة (COGS).</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Lifecycle Flow diagram -->
                        <div class="lifecycle-flow">
                            <h4 class="flow-title">دورة حياة التجارة المتكاملة: من الشراء إلى البيع والتحصيل</h4>
                            <div class="flow-steps">
                                <div class="flow-step">
                                    <div class="step-num">1</div>
                                    <div class="step-content">
                                        <strong>أمر الشراء PO</strong>
                                        <span>تأكيد طلب التوريد مع المورد</span>
                                    </div>
                                </div>
                                <div class="flow-arrow">←</div>
                                <div class="flow-step">
                                    <div class="step-num">2</div>
                                    <div class="step-content">
                                        <strong>استلام البضاعة Receipt</strong>
                                        <span>فحص الجودة وإدخال الدفعة وفتح طبقة FIFO</span>
                                    </div>
                                </div>
                                <div class="flow-arrow">←</div>
                                <div class="flow-step">
                                    <div class="step-num">3</div>
                                    <div class="step-content">
                                        <strong>المخزون وWMS</strong>
                                        <span>تخزين البضاعة في الحاويات Bins وتحديث الرصيد</span>
                                    </div>
                                </div>
                                <div class="flow-arrow">←</div>
                                <div class="flow-step">
                                    <div class="step-num">4</div>
                                    <div class="step-content">
                                        <strong>البيع والفوترة</strong>
                                        <span>حجز المخزون، إصدار الفاتورة أو نقطة البيع</span>
                                    </div>
                                </div>
                                <div class="flow-arrow">←</div>
                                <div class="flow-step">
                                    <div class="step-num">5</div>
                                    <div class="step-content">
                                        <strong>الصرف والترحيل المالي</strong>
                                        <span>إخراج المخزون وتوليد قيد اليومية الآلي</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flow-footer-action">
                                <el-button type="primary" size="small" :icon="Operation" @click="activeViewTab = 'diagrams'">
                                    استعراض المخططات التفاعلية التفصيلية (6 مسارات عمل)
                                    <el-icon class="el-icon--left"><ArrowLeft /></el-icon>
                                </el-button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Dynamic Module Documentation Cards -->
                <div v-if="filteredSections.length === 0" class="empty-docs-state">
                    <el-icon :size="48"><Search /></el-icon>
                    <h3>لم يتم العثور على نتائج تطابق "{{ searchQuery }}"</h3>
                    <p>جرب استخدام كلمات بحث بديلة مثل: مبيعات، فواتير، مستودع، قيد، رواتب، أو صلاحيات.</p>
                    <el-button type="primary" plain @click="clearSearch">إعادة تعيين البحث</el-button>
                </div>

                <section
                    v-for="section in filteredSections"
                    :key="section.id"
                    :id="section.id"
                    class="doc-section-card"
                    :class="`theme-${section.theme}`"
                >
                    <!-- Section Header -->
                    <div class="section-card-header" @click="toggleSection(section.id)">
                        <div class="header-main">
                            <div class="section-icon-wrap" :style="{ backgroundColor: section.color + '15', color: section.color }">
                                <el-icon :size="24"><component :is="section.icon" /></el-icon>
                            </div>
                            <div class="header-titles">
                                <div class="header-tag-row">
                                    <el-tag size="small" :color="section.color + '20'" :style="{ color: section.color, borderColor: section.color + '40' }">
                                        {{ section.category }}
                                    </el-tag>
                                    <span class="sub-counter">{{ section.items.length }} وظائف رئيسية</span>
                                    <button type="button" class="copy-anchor-btn small" @click.stop="copyAnchor(section.id)" title="نسخ رابط هذا القسم">
                                        <el-icon><Link /></el-icon>
                                    </button>
                                </div>
                                <h3 class="section-heading">{{ section.title }}</h3>
                                <p class="section-summary">{{ section.description }}</p>
                            </div>
                        </div>

                        <div class="header-actions">
                            <router-link
                                v-if="section.primaryRoute"
                                :to="section.primaryRoute"
                                class="quick-jump-btn"
                                @click.stop
                            >
                                <span>الانتقال للوحدة</span>
                                <el-icon><ArrowLeft /></el-icon>
                            </router-link>
                            <button type="button" class="expand-icon-btn" :aria-label="isSectionExpanded(section.id) ? 'طي القسم' : 'توسيع القسم'">
                                <el-icon :class="{ rotated: isSectionExpanded(section.id) }"><ArrowDown /></el-icon>
                            </button>
                        </div>
                    </div>

                    <!-- Collapsible Section Body -->
                    <transition name="slide-fade">
                        <div v-show="isSectionExpanded(section.id)" class="section-card-body">
                            <!-- Available Screens & Quick Links Bar -->
                            <div v-if="section.screens && section.screens.length" class="screens-bar">
                                <span class="screens-title">الشاشات والمسارات التابعة في النظام:</span>
                                <div class="screen-chips">
                                    <router-link
                                        v-for="scr in section.screens"
                                        :key="scr.path"
                                        :to="scr.path"
                                        class="screen-chip"
                                    >
                                        <el-icon><Monitor /></el-icon>
                                        <span>{{ scr.name }}</span>
                                        <code class="chip-path">{{ scr.path }}</code>
                                    </router-link>
                                </div>
                            </div>

                            <!-- Features / Functions List -->
                            <div class="features-grid">
                                <div
                                    v-for="(item, idx) in section.items"
                                    :key="idx"
                                    class="feature-card"
                                >
                                    <div class="feature-head">
                                        <div class="feature-num">{{ idx + 1 }}</div>
                                        <h4 class="feature-title">{{ item.name }}</h4>
                                    </div>
                                    <p class="feature-desc">{{ item.description }}</p>

                                    <!-- Operational Procedure / SOP -->
                                    <div v-if="item.steps && item.steps.length" class="feature-steps-box">
                                        <span class="steps-heading">
                                            <el-icon><Check /></el-icon> خطوات التنفيذ العملية (SOP):
                                        </span>
                                        <ol class="steps-list">
                                            <li v-for="(step, sIdx) in item.steps" :key="sIdx">
                                                {{ step }}
                                            </li>
                                        </ol>
                                    </div>

                                    <!-- Business / Financial / Inventory Rules -->
                                    <div v-if="item.rules && item.rules.length" class="feature-rules-box">
                                        <span class="rules-heading">
                                            <el-icon><WarningFilled /></el-icon> القواعد والضوابط النظامية:
                                        </span>
                                        <ul class="rules-list">
                                            <li v-for="(rule, rIdx) in item.rules" :key="rIdx">
                                                {{ rule }}
                                            </li>
                                        </ul>
                                    </div>

                                    <!-- Required Permissions -->
                                    <div v-if="item.permissions && item.permissions.length" class="feature-perm-row">
                                        <span class="perm-label">الصلاحيات المطلوبة:</span>
                                        <div class="perm-tags">
                                            <span v-for="perm in item.permissions" :key="perm" class="perm-tag">
                                                {{ perm }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section Deep Note / Best Practice Alert -->
                            <div v-if="section.deepNote" class="section-deep-note">
                                <div class="note-icon"><el-icon><InfoFilled /></el-icon></div>
                                <div class="note-content">
                                    <strong>إرشادات تشغيلية وممارسات مثلى:</strong>
                                    <p>{{ section.deepNote }}</p>
                                </div>
                            </div>
                        </div>
                    </transition>
                </section>
            </main>
        </div>

        <!-- TAB: WORKFLOW & ARCHITECTURE DIAGRAMS STUDIO -->
        <div v-show="activeViewTab === 'diagrams'" class="diagrams-tab-content">
            <div class="diagrams-intro">
                <div class="diagrams-intro-text">
                    <h2>
                        <el-icon><Operation /></el-icon>
                        مركز مخططات تدفق العمليات والمعمارية التشغيلية (Flowcharts Studio)
                    </h2>
                    <p>
                        مخططات بصرية تفاعلية توضح المسار الفيزيائي لحركة البضائع والمسار المحاسبي للأموال والقيود التلقائية عبر كافة دورات النظام. انقر على أي محطة لفحص بياناتها وقيدها المحاسبي وصلاحياتها وشاشتها.
                    </p>
                </div>

                <!-- Diagrams Selector Pills -->
                <div class="diagram-selector-pills">
                    <button
                        v-for="diag in diagramsList"
                        :key="diag.id"
                        type="button"
                        class="diag-pill"
                        :class="{ active: selectedDiagramId === diag.id }"
                        @click="selectDiagram(diag.id)"
                    >
                        <el-icon><component :is="diag.icon" /></el-icon>
                        <span class="diag-pill-title">{{ diag.title }}</span>
                        <span class="diag-pill-badge">{{ diag.nodes.length }} محطات</span>
                    </button>
                </div>
            </div>

            <!-- Active Diagram Workspace -->
            <div class="diagram-workspace">
                <div class="diagram-canvas-card">
                    <div class="canvas-header">
                        <div class="canvas-title-wrap">
                            <span class="canvas-tag">المخطط التفاعلي النشط</span>
                            <h3 class="canvas-title">{{ currentDiagram.title }}</h3>
                            <p class="canvas-desc">{{ currentDiagram.description }}</p>
                        </div>
                        <div class="canvas-meta">
                            <span class="nodes-count"><el-icon><Guide /></el-icon> {{ currentDiagram.nodes.length }} مراحل إجرائية</span>
                            <el-button size="small" type="primary" plain :icon="Printer" @click="printGuide">طباعة المخطط</el-button>
                        </div>
                    </div>

                    <!-- Flow Nodes Visual Layout -->
                    <div class="flowchart-container" dir="rtl">
                        <div
                            v-for="(node, nIdx) in currentDiagram.nodes"
                            :key="node.id"
                            class="flow-node-wrapper"
                        >
                            <!-- The Node Box -->
                            <div
                                class="flow-node-card"
                                :class="{
                                    active: selectedDiagramNode?.id === node.id,
                                    [`tone-${node.tone || 'blue'}`]: true
                                }"
                                @click="selectNode(node)"
                            >
                                <div class="node-top-bar">
                                    <span class="node-step-index">0{{ nIdx + 1 }}</span>
                                    <span class="node-doc-tag">{{ node.docType }}</span>
                                </div>
                                <div class="node-main">
                                    <div class="node-icon-box" :style="{ backgroundColor: node.color + '18', color: node.color }">
                                        <el-icon :size="20"><component :is="node.icon" /></el-icon>
                                    </div>
                                    <div class="node-text">
                                        <h4 class="node-title">{{ node.title }}</h4>
                                        <span class="node-role">{{ node.stage }}</span>
                                    </div>
                                </div>
                                <p class="node-short-desc">{{ node.summary }}</p>

                                <div class="node-impact-pills">
                                    <span v-if="node.hasStockEffect" class="impact-pill stock" title="يؤثر على رصيد المخزون">
                                        <el-icon><Box /></el-icon> أثر مخزني
                                    </span>
                                    <span v-if="node.hasJournalEntry" class="impact-pill journal" title="يولد قيد يومية آلي">
                                        <el-icon><Coin /></el-icon> قيد آلي
                                    </span>
                                </div>
                            </div>

                            <!-- Connector Arrow between nodes -->
                            <div v-if="nIdx < currentDiagram.nodes.length - 1" class="flow-connector">
                                <div class="connector-line"></div>
                                <span class="connector-label">{{ node.nextAction || 'ثم' }}</span>
                                <div class="connector-arrow">←</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selected Node Inspector Panel -->
                <div v-if="selectedDiagramNode" class="node-inspector-card">
                    <div class="inspector-header" :style="{ borderTopColor: selectedDiagramNode.color }">
                        <div class="inspector-title-row">
                            <div class="insp-icon" :style="{ backgroundColor: selectedDiagramNode.color + '20', color: selectedDiagramNode.color }">
                                <el-icon :size="22"><component :is="selectedDiagramNode.icon" /></el-icon>
                            </div>
                            <div>
                                <span class="insp-tag">تفاصيل المحطة المحددة في المخطط</span>
                                <h3 class="insp-title">{{ selectedDiagramNode.title }}</h3>
                                <span class="insp-doc">{{ selectedDiagramNode.docType }} · {{ selectedDiagramNode.stage }}</span>
                            </div>
                        </div>
                        <router-link
                            v-if="selectedDiagramNode.route"
                            :to="selectedDiagramNode.route"
                            class="insp-screen-btn"
                        >
                            <span>الانتقال للشاشة</span>
                            <el-icon><ArrowLeft /></el-icon>
                        </router-link>
                    </div>

                    <div class="inspector-body">
                        <p class="insp-desc">{{ selectedDiagramNode.description }}</p>

                        <div class="insp-details-grid">
                            <!-- Model & Database Entity -->
                            <div class="insp-detail-box">
                                <span class="box-lbl"><el-icon><Cpu /></el-icon> الكيان والنموذج في الكود:</span>
                                <code>{{ selectedDiagramNode.model }}</code>
                            </div>

                            <!-- Permission Required -->
                            <div class="insp-detail-box">
                                <span class="box-lbl"><el-icon><Setting /></el-icon> الصلاحية المطلوبة:</span>
                                <span class="perm-badge">{{ selectedDiagramNode.permission || 'متاحة للجميع' }}</span>
                            </div>

                            <!-- Inventory Impact -->
                            <div class="insp-detail-box" :class="{ 'has-effect': selectedDiagramNode.hasStockEffect }">
                                <span class="box-lbl"><el-icon><Box /></el-icon> الأثر المخزني الفعلي:</span>
                                <span class="detail-val">{{ selectedDiagramNode.stockImpact || 'لا يؤثر على رصيد المخزون' }}</span>
                            </div>

                            <!-- Accounting Entry -->
                            <div class="insp-detail-box" :class="{ 'has-effect': selectedDiagramNode.hasJournalEntry }">
                                <span class="box-lbl"><el-icon><Coin /></el-icon> القيد المحاسبي المتولد تلقائياً:</span>
                                <div v-if="selectedDiagramNode.journalEntry" class="journal-mini-lines">
                                    <div class="j-mini-line debit">
                                        <span class="j-side">مدين (Dr):</span>
                                        <span class="j-acc">{{ selectedDiagramNode.journalEntry.debit }}</span>
                                    </div>
                                    <div class="j-mini-line credit">
                                        <span class="j-side">دائن (Cr):</span>
                                        <span class="j-acc">{{ selectedDiagramNode.journalEntry.credit }}</span>
                                    </div>
                                </div>
                                <span v-else class="detail-val text-muted">لا يولد قيداً محاسبياً (وثيقة تشغيلية)</span>
                            </div>
                        </div>

                        <!-- Operational Steps for this node -->
                        <div v-if="selectedDiagramNode.steps && selectedDiagramNode.steps.length" class="insp-steps-box">
                            <span class="insp-steps-title"><el-icon><Check /></el-icon> خطوات التنفيذ في هذه المحطة:</span>
                            <ol class="insp-steps-list">
                                <li v-for="(st, stIdx) in selectedDiagramNode.steps" :key="stIdx">{{ st }}</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: INTERACTIVE SIMULATORS & CALCULATORS -->
        <div v-show="activeViewTab === 'simulators'" class="simulators-tab-content">
            <div class="simulators-intro">
                <h2>
                    <el-icon><Cpu /></el-icon>
                    المحاكيات والحاسبات التفاعلية لقواعد النظام
                </h2>
                <p>
                    أدوات تفاعلية تتيح لك تجربة محاكاة مباشرة للمعادلات الحسابية، استهلاك طبقات FIFO، واحتساب أرصدة المخزون والقيود المحاسبية الآلية كما تنفذ في الكود البرمجي تماماً.
                </p>
            </div>

            <div class="simulators-grid">
                <!-- 1. Sellable Stock Calculator -->
                <div class="simulator-card">
                    <div class="sim-card-header">
                        <div class="sim-icon" style="background: #2563eb18; color: #2563eb;">
                            <el-icon :size="20"><Box /></el-icon>
                        </div>
                        <div>
                            <h3>حاسبة المخزون القابل للبيع (Sellable Stock)</h3>
                            <span class="sim-subtitle">معادلة الدِلاء الثلاثة والحجز الفعلي</span>
                        </div>
                    </div>
                    <div class="sim-card-body">
                        <div class="sim-inputs-grid">
                            <div class="sim-input-item">
                                <label>الكمية السليمة (Available):</label>
                                <el-input-number v-model="simStock.available" :min="0" :step="5" />
                            </div>
                            <div class="sim-input-item">
                                <label>الكمية التالفة (Damaged):</label>
                                <el-input-number v-model="simStock.damaged" :min="0" :step="1" />
                            </div>
                            <div class="sim-input-item">
                                <label>المحجور للفحص (Quarantined):</label>
                                <el-input-number v-model="simStock.quarantined" :min="0" :step="1" />
                            </div>
                            <div class="sim-input-item">
                                <label>المحجوز لأوامر بيع (Reserved):</label>
                                <el-input-number v-model="simStock.reserved" :min="0" :step="5" />
                            </div>
                        </div>

                        <!-- Results display -->
                        <div class="sim-result-box">
                            <div class="sim-kpi-row">
                                <div class="sim-kpi">
                                    <span class="kpi-label">الإجمالي الفعلي على الرف:</span>
                                    <span class="kpi-val">{{ simTotalStock }} وحدة</span>
                                </div>
                                <div class="sim-kpi highlight">
                                    <span class="kpi-label">المتاح للبيع في الفواتير (Sellable):</span>
                                    <span class="kpi-val green">{{ simSellableStock }} وحدة</span>
                                </div>
                            </div>

                            <!-- Progress Distribution Bar -->
                            <div class="stock-dist-bar-wrap">
                                <span class="dist-label">توزيع الحصص في المستودع:</span>
                                <div class="stock-dist-bar">
                                    <div class="bar-segment sellable" :style="{ width: simSellablePercent + '%' }" :title="`قابل للبيع: ${simSellableStock}`"></div>
                                    <div class="bar-segment reserved" :style="{ width: simReservedPercent + '%' }" :title="`محجوز: ${simStock.reserved}`"></div>
                                    <div class="bar-segment damaged" :style="{ width: simDamagedPercent + '%' }" :title="`تالف: ${simStock.damaged}`"></div>
                                    <div class="bar-segment quarantined" :style="{ width: simQuarantinedPercent + '%' }" :title="`محجور: ${simStock.quarantined}`"></div>
                                </div>
                                <div class="bar-legend">
                                    <span class="leg-item"><span class="dot sellable"></span> متاح للبيع ({{ simSellablePercent }}%)</span>
                                    <span class="leg-item"><span class="dot reserved"></span> محجوز ({{ simReservedPercent }}%)</span>
                                    <span class="leg-item"><span class="dot damaged"></span> تالف ({{ simDamagedPercent }}%)</span>
                                    <span class="leg-item"><span class="dot quarantined"></span> محجور ({{ simQuarantinedPercent }}%)</span>
                                </div>
                            </div>

                            <div class="sim-formula-note">
                                <strong>المعادلة البرمجية في الكود:</strong>
                                <code>available_stock = max(0, available_quantity - reserved_quantity)</code>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. FIFO Cost Layers Breakdown Simulator -->
                <div class="simulator-card">
                    <div class="sim-card-header">
                        <div class="sim-icon" style="background: #05966918; color: #059669;">
                            <el-icon :size="20"><Coin /></el-icon>
                        </div>
                        <div>
                            <h3>حاسبة طبقات تكلفة FIFO (الوارد أولاً يصرف أولاً)</h3>
                            <span class="sim-subtitle">احتساب تكلفة البضاعة المباعة والمخزون المتبقي</span>
                        </div>
                    </div>
                    <div class="sim-card-body">
                        <div class="fifo-layers-list">
                            <span class="fifo-layers-title">الطبقات الواردة المتتالية للمستودع:</span>
                            <div class="layer-rows">
                                <div v-for="(layer, lIdx) in simFifo.layers" :key="lIdx" class="layer-row">
                                    <span class="layer-badge">شحنة {{ lIdx + 1 }}</span>
                                    <span class="layer-qty">الكمية: <strong>{{ layer.quantity }}</strong> وحدة</span>
                                    <span class="layer-cost">سعر التكلفة: <strong>{{ layer.cost }}</strong> ر.س</span>
                                    <span class="layer-total">الإجمالي: {{ (layer.quantity * layer.cost).toLocaleString() }} ر.س</span>
                                </div>
                            </div>
                        </div>

                        <div class="fifo-sell-input">
                            <label>الكمية المراد بيعها في الفاتورة:</label>
                            <el-input-number v-model="simFifo.sellQty" :min="1" :max="simFifoTotalQty" :step="5" />
                            <span class="max-hint">الحد الأقصى المتاح: {{ simFifoTotalQty }} وحدة</span>
                        </div>

                        <!-- Live FIFO Calculation Output -->
                        <div class="fifo-output-box">
                            <h4>نتيجة استهلاك طبقات FIFO:</h4>
                            <div class="consumption-steps">
                                <div v-for="(step, sIdx) in simFifoResult.consumedSteps" :key="sIdx" class="c-step">
                                    <el-icon><Check /></el-icon>
                                    <span>تم سحب <strong>{{ step.qty }}</strong> وحدة من (شحنة {{ step.layerIndex + 1 }}) بتكلفة {{ step.cost }} ر.س = <strong>{{ step.totalCost.toLocaleString() }}</strong> ر.س</span>
                                </div>
                            </div>

                            <div class="fifo-totals-row">
                                <div class="fifo-stat">
                                    <span>إجمالي تكلفة البضاعة المباعة (COGS):</span>
                                    <strong>{{ simFifoResult.totalCogs.toLocaleString() }} ر.س</strong>
                                </div>
                                <div class="fifo-stat">
                                    <span>متوسط تكلفة الوحدة المباعة:</span>
                                    <strong>{{ simFifoResult.avgCostPerUnit.toFixed(2) }} ر.س / وحدة</strong>
                                </div>
                                <div class="fifo-stat">
                                    <span>قيمة المخزون المتبقي على الرف:</span>
                                    <strong class="text-blue">{{ simFifoResult.remainingStockValue.toLocaleString() }} ر.س</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Dynamic Reorder Point Simulator -->
                <div class="simulator-card">
                    <div class="sim-card-header">
                        <div class="sim-icon" style="background: #d9770618; color: #d97706;">
                            <el-icon :size="20"><Odometer /></el-icon>
                        </div>
                        <div>
                            <h3>حاسبة نقطة إعادة الطلب الديناميكية (ROP)</h3>
                            <span class="sim-subtitle">تأمين المخزون ضد النفاد والركود</span>
                        </div>
                    </div>
                    <div class="sim-card-body">
                        <div class="sim-inputs-grid">
                            <div class="sim-input-item">
                                <label>متوسط البيع اليومي (آخر 30 يوماً):</label>
                                <el-input-number v-model="simRop.dailySales" :min="1" :step="1" />
                            </div>
                            <div class="sim-input-item">
                                <label>مهلة التوريد من المصنع (بالأيام):</label>
                                <el-input-number v-model="simRop.leadTimeDays" :min="1" :step="1" />
                            </div>
                            <div class="sim-input-item">
                                <label>مخزون الأمان الوقائي (Safety Stock):</label>
                                <el-input-number v-model="simRop.safetyStock" :min="0" :step="5" />
                            </div>
                        </div>

                        <div class="sim-result-box rop-result">
                            <div class="rop-summary">
                                <div class="rop-num-box">
                                    <span class="rop-label">نقطة إعادة الطلب المحتسبة:</span>
                                    <span class="rop-num">{{ simCalculatedRop }} وحدة</span>
                                </div>
                                <p class="rop-advice">
                                    إذا انخفض الرصيد المتاح للصنف إلى <strong>{{ simCalculatedRop }} وحدة</strong> أو أقل، يرسل النظام إشعاراً عاجلاً لمسؤول المشتريات لإصدار أمر شراء بكمية تغطي الاحتياج قبل نفاد البضاعة.
                                </p>
                            </div>
                            <div class="sim-formula-note">
                                <strong>المعادلة:</strong> <code>ROP = ceil(متوسط البيع اليومي × مهلة التوريد + مخزون الأمان)</code>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Double-Entry Journal Entries Simulator -->
                <div class="simulator-card">
                    <div class="sim-card-header">
                        <div class="sim-icon" style="background: #7c3aed18; color: #7c3aed;">
                            <el-icon :size="20"><Tickets /></el-icon>
                        </div>
                        <div>
                            <h3>محاكي القيود المحاسبية التلقائية (Double-Entry Engine)</h3>
                            <span class="sim-subtitle">استعراض القيود الآلية المتولدة عن كل عملية</span>
                        </div>
                    </div>
                    <div class="sim-card-body">
                        <div class="op-selector-row">
                            <label>اختر العملية التجارية المحاكية:</label>
                            <el-select v-model="selectedJournalOp" placeholder="اختر العملية" size="large" style="width: 100%;">
                                <el-option
                                    v-for="op in journalOperations"
                                    :key="op.id"
                                    :label="op.name"
                                    :value="op.id"
                                />
                            </el-select>
                        </div>

                        <!-- Journal Entry Table -->
                        <div class="journal-preview-table-wrap">
                            <div class="journal-op-desc">
                                <el-icon><InfoFilled /></el-icon>
                                <span>{{ currentJournalOp.description }}</span>
                            </div>

                            <table class="journal-table">
                                <thead>
                                    <tr>
                                        <th>رقم الحساب</th>
                                        <th>اسم الحساب في الدليل</th>
                                        <th class="text-right">مدين (Debit)</th>
                                        <th class="text-right">دائن (Credit)</th>
                                        <th>البيان والتوضيح</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, lIdx) in currentJournalOp.lines" :key="lIdx" :class="{ 'credit-line': line.credit > 0 }">
                                        <td><code>{{ line.accountCode }}</code></td>
                                        <td><strong>{{ line.accountName }}</strong></td>
                                        <td class="text-right font-mono">{{ line.debit ? line.debit.toLocaleString() + ' ر.س' : '-' }}</td>
                                        <td class="text-right font-mono">{{ line.credit ? line.credit.toLocaleString() + ' ر.س' : '-' }}</td>
                                        <td class="text-muted">{{ line.note }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="totals-row">
                                        <td colspan="2">الإجمالي والتوازن:</td>
                                        <td class="text-right font-mono text-green">{{ currentJournalOpTotalDebit.toLocaleString() }} ر.س</td>
                                        <td class="text-right font-mono text-green">{{ currentJournalOpTotalCredit.toLocaleString() }} ر.س</td>
                                        <td><span class="balanced-badge"><el-icon><Check /></el-icon> قيد متوازن 100%</span></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: SYSTEM RULES & FINANCIAL POLICIES -->
        <div v-show="activeViewTab === 'rules'" class="rules-tab-content">
            <div class="rules-intro">
                <h2>
                    <el-icon><WarningFilled /></el-icon>
                    مصفوفة القواعد المحاسبية والسياسات الصارمة في النظام
                </h2>
                <p>
                    مجموعة القواعد التي تم بناؤها في الكود لمنع حدوث التناقضات المالية أو المخزنية، ولا يمكن تجاوزها إلا بصلاحيات استثنائية.
                </p>
            </div>

            <div class="rules-categories-grid">
                <div v-for="cat in masterRulesCategories" :key="cat.title" class="rule-cat-card">
                    <div class="rule-cat-head" :style="{ borderTopColor: cat.color }">
                        <el-icon :style="{ color: cat.color }"><component :is="cat.icon" /></el-icon>
                        <h3>{{ cat.title }}</h3>
                    </div>
                    <div class="rule-cat-body">
                        <div v-for="(r, rIdx) in cat.rules" :key="rIdx" class="master-rule-item">
                            <div class="rule-badge-row">
                                <span class="rule-code-badge">{{ r.code }}</span>
                                <span class="rule-enforcement" :class="r.severity">{{ r.severityText }}</span>
                            </div>
                            <h4 class="rule-name">{{ r.title }}</h4>
                            <p class="rule-desc">{{ r.description }}</p>
                            <div v-if="r.formula" class="rule-formula-box">
                                <code>{{ r.formula }}</code>
                            </div>
                            <div v-if="r.consequence" class="rule-consequence">
                                <el-icon><Warning /></el-icon>
                                <span><strong>الأثر عند المخالفة:</strong> {{ r.consequence }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: KEYBOARD SHORTCUTS & CHEAT SHEET -->
        <div v-show="activeViewTab === 'shortcuts'" class="shortcuts-tab-content">
            <div class="shortcuts-intro">
                <h2>
                    <el-icon><Tickets /></el-icon>
                    دليل اختصارات لوحة المفاتيح والإنتاجية العالية
                </h2>
                <p>
                    صممت شاشات الفوترة، نقطة البيع (POS)، والبحث السريع لتتيح للموظف العمل كاملاً باستخدام لوحة المفاتيح دون لمس الفأرة لتسريع خدمة العملاء.
                </p>
            </div>

            <div class="shortcuts-tables-grid">
                <div class="shortcut-block">
                    <div class="block-head">
                        <el-icon><ShoppingCart /></el-icon>
                        <h3>شاشة إنشاء الفاتورة ونقطة البيع (Sales & POS)</h3>
                    </div>
                    <table class="shortcuts-table">
                        <thead>
                            <tr>
                                <th>الاختصار</th>
                                <th>الوظيفة والإجراء</th>
                                <th>الشاشات المدعومة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><kbd>F2</kbd></td>
                                <td>التركيز المباشر على حقل مسح الباركود / البحث عن صنف</td>
                                <td>الفاتورة، POS</td>
                            </tr>
                            <tr>
                                <td><kbd>Enter</kbd></td>
                                <td>إضافة الصنف الممسوح بالباركود وتثبيت الكمية</td>
                                <td>الفاتورة، POS</td>
                            </tr>
                            <tr>
                                <td><kbd>F4</kbd></td>
                                <td>تفعيل / إلغاء "وضع التركيز" (Focus Mode) لإخفاء القوائم وتوسيع الشاشة</td>
                                <td>الفاتورة</td>
                            </tr>
                            <tr>
                                <td><kbd>Ctrl</kbd> + <kbd>Enter</kbd></td>
                                <td>حفظ وترحيل الفاتورة فورياً وفتح نافذة الدفع</td>
                                <td>الفاتورة، POS</td>
                            </tr>
                            <tr>
                                <td><kbd>F9</kbd></td>
                                <td>تحويل نمط العميل فوراً إلى "عميل عابر" (Walk-in) دون فتح حساب</td>
                                <td>الفاتورة، POS</td>
                            </tr>
                            <tr>
                                <td><kbd>Esc</kbd></td>
                                <td>إغلاق النوافذ المنبثقة، تفريغ حقل البحث، أو الخروج من وضع التعديل</td>
                                <td>جميع الشاشات</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="shortcut-block">
                    <div class="block-head">
                        <el-icon><Search /></el-icon>
                        <h3>التنقل والبحث العام في لوحة التحكم</h3>
                    </div>
                    <table class="shortcuts-table">
                        <thead>
                            <tr>
                                <th>الاختصار</th>
                                <th>الوظيفة والإجراء</th>
                                <th>الشاشات المدعومة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><kbd>Ctrl</kbd> + <kbd>K</kbd> / <kbd>⌘</kbd> + <kbd>K</kbd></td>
                                <td>فتح نافذة البحث السريع الشامل (منتجات، عملاء، فواتير، موظفين)</td>
                                <td>جميع صفحات لوحة التحكم</td>
                            </tr>
                            <tr>
                                <td><kbd>/</kbd></td>
                                <td>التركيز الفوري على حقل البحث في القائمة الجانبية أو صفحة التوثيق</td>
                                <td>القائمة الجانبية، التوثيق</td>
                            </tr>
                            <tr>
                                <td><kbd>↑</kbd> <kbd>↓</kbd></td>
                                <td>التنقل بين نتائج البحث المعروضة في القائمة المنسدلة</td>
                                <td>شريط البحث</td>
                            </tr>
                            <tr>
                                <td><kbd>Tab</kbd></td>
                                <td>الانتقال إلى الحقل التالي في جداول إدخال القيود والفواتير</td>
                                <td>جداول الإدخال</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 5: GLOSSARY & FAQ -->
        <div v-show="activeViewTab === 'glossary_faq'" class="glossary-tab-content">
            <!-- Glossary -->
            <section id="system-glossary" class="doc-section-card glossary-card">
                <div class="section-card-header" @click="toggleSection('system-glossary')">
                    <div class="header-main">
                        <div class="section-icon-wrap" style="background-color: #8b5cf618; color: #8b5cf6;">
                            <el-icon :size="24"><Collection /></el-icon>
                        </div>
                        <div class="header-titles">
                            <div class="header-tag-row">
                                <el-tag size="small" type="warning">المفاهيم المحاسبية والتقنية</el-tag>
                            </div>
                            <h3 class="section-heading">قاموس المصطلحات والمفاهيم المحورية</h3>
                            <p class="section-summary">تعريف دقيق للمصطلحات الرياضية، المحاسبية، والمخزنية المعتمدة داخل النظام.</p>
                        </div>
                    </div>
                    <button type="button" class="expand-icon-btn">
                        <el-icon :class="{ rotated: isSectionExpanded('system-glossary') }"><ArrowDown /></el-icon>
                    </button>
                </div>

                <transition name="slide-fade">
                    <div v-show="isSectionExpanded('system-glossary')" class="section-card-body">
                        <div class="glossary-grid">
                            <div v-for="term in glossaryTerms" :key="term.term" class="glossary-box">
                                <div class="term-header">
                                    <span class="term-ar">{{ term.term }}</span>
                                    <span class="term-en">{{ term.en }}</span>
                                </div>
                                <p class="term-def">{{ term.definition }}</p>
                                <div class="term-formula" v-if="term.formula">
                                    <code>{{ term.formula }}</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </section>

            <!-- FAQ -->
            <section id="system-faq" class="doc-section-card faq-card">
                <div class="section-card-header" @click="toggleSection('system-faq')">
                    <div class="header-main">
                        <div class="section-icon-wrap" style="background-color: #06b6d418; color: #06b6d4;">
                            <el-icon :size="24"><QuestionFilled /></el-icon>
                        </div>
                        <div class="header-titles">
                            <div class="header-tag-row">
                                <el-tag size="small" type="info">دليل استكشاف الأخطاء</el-tag>
                            </div>
                            <h3 class="section-heading">الأسئلة الشائعة وحلول المشكلات التشغيلية</h3>
                            <p class="section-summary">إجابات مباشرة على أكثر المواقف والتساؤلات تكراراً في بيئة العمل اليومية.</p>
                        </div>
                    </div>
                    <button type="button" class="expand-icon-btn">
                        <el-icon :class="{ rotated: isSectionExpanded('system-faq') }"><ArrowDown /></el-icon>
                    </button>
                </div>

                <transition name="slide-fade">
                    <div v-show="isSectionExpanded('system-faq')" class="section-card-body">
                        <div class="faq-list">
                            <div v-for="(faq, fIdx) in faqList" :key="fIdx" class="faq-item">
                                <div class="faq-q">
                                    <el-icon class="q-icon"><ChatDotSquare /></el-icon>
                                    <span>{{ faq.q }}</span>
                                </div>
                                <div class="faq-a">
                                    {{ faq.a }}
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </section>
        </div>

        <!-- Back to Top Floating Action Button -->
        <transition name="fade">
            <button
                v-if="showBackToTop"
                type="button"
                class="back-to-top-btn"
                @click="scrollToTop"
                title="العودة لأعلى الصفحة"
            >
                <el-icon><Top /></el-icon>
            </button>
        </transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import {
    Reading, Search, Fold, Expand, Printer, Menu, Top, Monitor, Check,
    WarningFilled, InfoFilled, ArrowLeft, ArrowDown, Cpu, Connection, Coin,
    Box, ShoppingCart, Refresh, ShoppingBag, Location, UserFilled,
    ChatDotRound, Tools, DataAnalysis, Bell, View, Setting, Collection,
    QuestionFilled, ChatDotSquare, Odometer, Tickets, Timer, Link, Warning,
    Operation, Guide, Share, Van, OfficeBuilding, TrendCharts
} from '@element-plus/icons-vue';

const { t } = useI18n();

// Reactive State
const activeViewTab = ref('modules');
const searchQuery = ref('');
const selectedRole = ref('all');
const activeSectionId = ref('system-architecture');
const showBackToTop = ref(false);
const searchInputRef = ref(null);

const expandedSections = ref(new Set([
    'system-architecture',
    'sales-module',
    'inventory-module',
    'accounting-module',
    'wms-module',
    'purchases-module',
    'system-glossary',
    'system-faq'
]));

// Popular search tags for quick filtering
const popularSearchTags = [
    'المبيعات', 'الفواتير', 'المخزون', 'FIFO', 'القيود المحاسبية',
    'WMS', 'المستودعات', 'أوامر الشراء', 'الرواتب', 'الصلاحيات'
];

const applySearchTag = (tag) => {
    searchQuery.value = tag;
    activeViewTab.value = 'modules';
};

// Role Filter definitions
const rolesList = [
    { id: 'all', label: 'جميع الوظائف والوحدات', icon: Menu },
    { id: 'admin', label: 'مدير النظام والإدارة العليا', icon: Setting },
    { id: 'sales', label: 'المبيعات ونقاط البيع POS', icon: ShoppingCart },
    { id: 'inventory', label: 'المستودعات وسلسلة الإمداد', icon: Box },
    { id: 'accounting', label: 'المحاسبة والإدارة المالية', icon: Coin },
    { id: 'hr', label: 'الموارد البشرية وشؤون الموظفين', icon: UserFilled },
];

// Interactive Simulator 1: Sellable Stock State
// Flowchart Diagrams State
const selectedDiagramId = ref('master');
const selectedDiagramNode = ref(null);

const diagramsList = [
    {
        id: 'master',
        title: 'المخطط الشامل لدورة التجارة والمالية (End-to-End Enterprise Flow)',
        description: 'التدفق الكامل من التعاقد مع المورد وإصدار أمر الشراء إلى استلام المخزون، الحجز للعملاء، الفوترة، والصرف والترحيل المحاسبي.',
        icon: Operation,
        nodes: [
            {
                id: 'm-po',
                title: 'أمر الشراء والتوريد',
                stage: 'إدارة المشتريات',
                docType: 'PO-######',
                summary: 'إصدار وتأكيد أمر الشراء مع المورد الخارجي أو المصنع.',
                color: '#d97706',
                tone: 'amber',
                icon: ShoppingBag,
                model: 'App\\Models\\PurchaseOrder',
                permission: 'purchases.orders.view',
                route: '/admin/purchases/orders',
                hasStockEffect: false,
                hasJournalEntry: false,
                stockImpact: 'لا يؤثر على رصيد المخزون الفعلي (وثيقة تعاقدية)',
                journalEntry: null,
                nextAction: 'وصول الشحنة',
                description: 'وثيقة رسمية يتم إرسالها للمورد تحدد الكميات، الأسعار المتفق عليها، مهلة التوريد، ومستودع الاستلام المستهدف.',
                steps: [
                    'اختيار المورد وتحديد شروط الائتمان ومستودع الاستلام والعملة.',
                    'إضافة أصناف المواد الصحية ومراجعة التكلفة التقديرية.',
                    'اعتماد أمر الشراء ومتابعة جدول الشحن.'
                ]
            },
            {
                id: 'm-receipt',
                title: 'إذن الاستلام المخزني',
                stage: 'المستودعات والاستلام',
                docType: 'RCV-######',
                summary: 'فحص الشحنة واستلامها وفتح طبقة تكلفة FIFO جديدة.',
                color: '#059669',
                tone: 'emerald',
                icon: Box,
                model: 'App\\Models\\PurchaseReceipt',
                permission: 'purchases.receipts.view',
                route: '/admin/purchases/receipts',
                hasStockEffect: true,
                hasJournalEntry: true,
                stockImpact: 'زيادة available_quantity على الرف فوراً وفتح طبقة FIFO بسعر التكلفة',
                journalEntry: {
                    debit: '10401 مخزون بضاعة الأدوات الصحية',
                    credit: '20101 الموردون والذمم الدائنة'
                },
                nextAction: 'تخزين في Bins',
                description: 'إثبات وصول البضاعة فعلياً إلى المستودع، حيث يتم فحصها وتوثيق الكميات السليمة والتالفة وفتح سجل التكلفة التاريخي.',
                steps: [
                    'فحص الشحنة ومطابقة الكميات لأمر الشراء PO.',
                    'تسجيل رقم تشغيلة الدفعة (Batch Number) وتاريخ الصلاحية.',
                    'تأكيد الاستلام لتغذية المخزون وتوليد قيد استحقاق المورد آلياً.'
                ]
            },
            {
                id: 'm-bins',
                title: 'توزيع حاويات WMS',
                stage: 'نظام إدارة المستودعات',
                docType: 'BIN-STORAGE',
                summary: 'إيداع الصنف في موقعه التخزيني الدقيق (ممر - رف - خانة).',
                color: '#0d9488',
                tone: 'teal',
                icon: Location,
                model: 'App\\Models\\WarehouseInventory',
                permission: 'wms.bins.view',
                route: '/admin/wms/bins',
                hasStockEffect: true,
                hasJournalEntry: false,
                stockImpact: 'ربط الكمية بالحاوية التخزينية المحددة bin_id وتحديث سعتها',
                journalEntry: null,
                nextAction: 'طلب بيع من عميل',
                description: 'تقسيم المخزون جغرافياً داخل المستودع لتسهيل وسرعة الوصول إليه أثناء عمليات تجهيز طلبيات البيع.',
                steps: [
                    'مسح باركود الحاوية وباركود الصنف عبر الجهاز الكفي.',
                    'تثبيت الكمية في الحاوية لضمان جاهزيتها للالتقاط.'
                ]
            },
            {
                id: 'm-so',
                title: 'أمر البيع وحجز المخزون',
                stage: 'إدارة المبيعات',
                docType: 'SO-######',
                summary: 'تأكيد طلب العميل وحجز المخزون السليم لمنع بيعه لآخرين.',
                color: '#2563eb',
                tone: 'blue',
                icon: ShoppingCart,
                model: 'App\\Models\\SalesOrder',
                permission: 'sales.orders.view',
                route: '/admin/sales/sales-orders',
                hasStockEffect: true,
                hasJournalEntry: false,
                stockImpact: 'نقل الكمية إلى reserved_quantity وتخفيض المخزون القابل للبيع sellable',
                journalEntry: null,
                nextAction: 'تحضير الطلبية',
                description: 'المرحلة التي تضمن للعميل توفر بضاعته؛ حيث يقوم النظام بقفل الكمية برمجياً دون تحريكها من الرف حتى الشحن.',
                steps: [
                    'استقبال أمر الشراء من العميل أو تحويل عرض سعر سابق.',
                    'اختيار الفرع أو المستودع المورد وتأكيد الحجز التلقائي.'
                ]
            },
            {
                id: 'm-picking',
                title: 'قائمة الالتقاط والتعبئة',
                stage: 'اللوجستيات والشحن',
                docType: 'PICK-PACK',
                summary: 'جمع الأصناف من الحاويات وتغليفها وإصدار ملصق الشحنة.',
                color: '#0284c7',
                tone: 'sky',
                icon: Van,
                model: 'App\\Models\\PickingList',
                permission: 'wms.picking.view',
                route: '/admin/wms/picking',
                hasStockEffect: false,
                hasJournalEntry: false,
                stockImpact: 'تحريك البضاعة من الحاوية إلى منطقة الشحن والتجهيز',
                journalEntry: null,
                nextAction: 'إصدار الفاتورة',
                description: 'تنظيم حركة عمال المستودع لجمع أصناف طلبيات البيع بأقصر مسار ممكن ومطابقة الباركود قبل الإرسال.',
                steps: [
                    'توليد قائمة الالتقاط والمسح الباركودي لكل صنف مجمع.',
                    'تحويل الشحنة لمحطة التغليف وإصدار بوليصة التسليم.'
                ]
            },
            {
                id: 'm-inv',
                title: 'فاتورة المبيعات المعتمدة',
                stage: 'الفوترة والمحاسبة',
                docType: 'INV-######',
                summary: 'ترحيل الفاتورة، خصم المخزون الحقيقي، وتوليد القيد المالي.',
                color: '#7c3aed',
                tone: 'purple',
                icon: Tickets,
                model: 'App\\Models\\Invoice',
                permission: 'sales.invoices.view',
                route: '/admin/sales/invoices',
                hasStockEffect: true,
                hasJournalEntry: true,
                stockImpact: 'خصم الكمية من available_quantity واستهلاك reserved_quantity وخروج طبقة FIFO',
                journalEntry: {
                    debit: '10201 حساب العميل (أو 10101 الصندوق) + 50101 تكلفة المبيعات COGS',
                    credit: '40101 إيراد المبيعات + 20301 ضريبة المخرجات + 10401 المخزون'
                },
                nextAction: 'التحصيل والسداد',
                description: 'الوثيقة الضريبية النهائية التي تقفل المعاملة التجارية، وتثبت خروج البضاعة من عهدة الشركة وتولد الإيراد والضريبة.',
                steps: [
                    'مراجعة الأصناف والأسعار وتطبيق الخصم والضريبة.',
                    'ترحيل الفاتورة وطباعة الفاتورة الضريبية الرسمية.'
                ]
            },
            {
                id: 'm-pay',
                title: 'التحصيل وسند القبض',
                stage: 'الخزينة والمالية',
                docType: 'PAY-######',
                summary: 'استلام الدفعة النقدية أو البنكية وتخفيض مديونية العميل.',
                color: '#10b981',
                tone: 'emerald',
                icon: Coin,
                model: 'App\\Models\\CustomerPayment',
                permission: 'sales.payments.view',
                route: '/admin/sales/payments',
                hasStockEffect: false,
                hasJournalEntry: true,
                stockImpact: 'لا يؤثر على رصيد المخزون',
                journalEntry: {
                    debit: '10101 الصندوق الرئيسي أو 10102 البنك التجاري',
                    credit: '10201 حساب العميل (تخفيض المديونية)'
                },
                nextAction: 'القوائم الختامية',
                description: 'توثيق دخول السيولة المالية إلى حسابات الشركة وإقفال الفواتير المفتوحة للعميل وتحديث كشف حسابه.',
                steps: [
                    'إدخال مبلغ الدفعة وتحديد طريقة القبض (نقدي، حوالة، شيك).',
                    'تخصيص الدفعة للفواتير الأقدم أو تسجيلها كرصيد دائن.'
                ]
            },
            {
                id: 'm-gl',
                title: 'الأستاذ العام والقوائم المالية',
                stage: 'التدقيق والقوائم الختامية',
                docType: 'GL-REPORTS',
                summary: 'تحديث ميزان المراجعة وقائمة الدخل والميزانية العمومية تلقائياً.',
                color: '#0f172a',
                tone: 'slate',
                icon: TrendCharts,
                model: 'App\\Models\\JournalEntry',
                permission: 'accounting.balance_sheet.view',
                route: '/admin/accounting/balance-sheet',
                hasStockEffect: false,
                hasJournalEntry: true,
                stockImpact: 'تقييم قيمة المخزون الختامي في بند الأصول المتداولة بالميزانية',
                journalEntry: {
                    debit: 'ترحيل أثر كافة القيود السابقة في الحسابات الختامية',
                    credit: 'توازن تداول الأصول والخصوم وحقوق الملكية'
                },
                nextAction: 'إقفال الفترة',
                description: 'المحطة الختامية التي تلخص سلامة النشاط المالي، واحتساب الأرباح التشغيلية، ومطابقة حركة النقدية والمخزون.',
                steps: [
                    'استخراج ميزان المراجعة للتحقق من التوازن الصفري التام.',
                    'مراجعة قائمة الدخل واحتساب مجمل وصافي الربح.'
                ]
            }
        ]
    },
    {
        id: 'sales',
        title: 'دورة حياة أمر البيع والفوترة والشحن (Sales & Fulfillment Lifecycle)',
        description: 'مسار المعاملة البيعية من عرض السعر وتحويله لأمر بيع، حجز المخزون، توجيه المستودع، الالتقاط والتعبئة، وإصدار الفاتورة النهائية.',
        icon: ShoppingCart,
        nodes: [
            {
                id: 's-quote',
                title: 'عرض السعر الترويجي',
                stage: 'المفاوضات البيعية',
                docType: 'QUO-######',
                summary: 'إصدار عرض سعر محدد المدة للعميل مع قائمة الأصناف والخصومات.',
                color: '#3b82f6',
                icon: Tickets,
                model: 'App\\Models\\Quote',
                permission: 'sales.quotes.view',
                route: '/admin/sales/quotes',
                hasStockEffect: false,
                hasJournalEntry: false,
                stockImpact: 'لا يؤثر على المخزون (عرض استرشادي)',
                journalEntry: null,
                nextAction: 'موافقة العميل',
                description: 'وثيقة تسعير مبدئية توضح الأسعار الخاصة والشروط التجارية للمشروع دون حجز مخزون.',
                steps: ['اختيار العميل وتحديد قائمة المواد الصحية المطلوبة.', 'تحديد مدة صلاحية العرض والشروط والخصم.']
            },
            {
                id: 's-order',
                title: 'أمر البيع وحجز البضاعة',
                stage: 'تأكيد الطلب والحجز',
                docType: 'SO-######',
                summary: 'تأكيد العميل وتحويل العرض لأمر بيع رسمي وحجز الكميات فورياً.',
                color: '#2563eb',
                icon: ShoppingCart,
                model: 'App\\Models\\SalesOrder',
                permission: 'sales.orders.view',
                route: '/admin/sales/sales-orders',
                hasStockEffect: true,
                hasJournalEntry: false,
                stockImpact: 'زيادة reserved_quantity وتأمين البضاعة في المستودع',
                journalEntry: null,
                nextAction: 'توجيه المخزون',
                description: 'الالتزام البيعي الذي يضمن عدم بيع نفس الكميات لأي عميل آخر في أي فرع.',
                steps: ['الضغط على "تحويل لأمر بيع" من شاشة عروض الأسعار.', 'فحص النظام للتوفر وحجز الكميات السليمة.']
            },
            {
                id: 's-routing',
                title: 'التوجيه الذكي للمستودع',
                stage: 'التوجيه اللوجستي',
                docType: 'WH-ROUTING',
                summary: 'تحديد أفضل مستودع لتسليم الطلبية وفق الموقع الجغرافي والرصيد.',
                color: '#6366f1',
                icon: OfficeBuilding,
                model: 'App\\Services\\Sales\\OrderRoutingService',
                permission: 'sales.orders.view',
                route: '/admin/sales/sales-orders',
                hasStockEffect: false,
                hasJournalEntry: false,
                stockImpact: 'تخصيص أمر الصرف لمستودع محدد',
                journalEntry: null,
                nextAction: 'إصدار إذن الالتقاط',
                description: 'محرك توجيه يفحص أرصدة كافة الفروع والمستودعات ويختار أقرب فرع يغطي كافة بنود الطلبية.',
                steps: ['مراجعة خريطة التوفر عبر الفروع.', 'تأكيد مستودع الصرف أو تجزئة الطلبية إذا لزم الأمر.']
            },
            {
                id: 's-pick',
                title: 'قائمة الالتقاط المخزني',
                stage: 'تجهيز الطلبية WMS',
                docType: 'PICK-######',
                summary: 'مسح باركود الحاويات وجمع الأصناف بالترتيب الأمثل للممرات.',
                color: '#0284c7',
                icon: Guide,
                model: 'App\\Models\\PickingList',
                permission: 'wms.picking.view',
                route: '/admin/wms/picking',
                hasStockEffect: false,
                hasJournalEntry: false,
                stockImpact: 'نقل البضاعة من الحاوية التخزينية لمنطقة التجميع',
                journalEntry: null,
                nextAction: 'التعبئة والشحن',
                description: 'توجيه عامل المستودع لأقصر مسار بين الرفوف لجمع الأصناف بدقة ومنع أخطاء المقاسات والموديلات.',
                steps: ['طباعة أو فتح قائمة الالتقاط على الجهاز الكفي.', 'مسح باركود الرف ثم الصنف للتأكيد.']
            },
            {
                id: 's-pack',
                title: 'محطة التعبئة وإذن الشحن',
                stage: 'التغليف والتسليم',
                docType: 'PACK-######',
                summary: 'فحص التغليف وإصدار ملصقات الصناديق وبوليصة الشاحنة.',
                color: '#0d9488',
                icon: Van,
                model: 'App\\Models\\PackingList',
                permission: 'wms.packing.view',
                route: '/admin/wms/packing',
                hasStockEffect: false,
                hasJournalEntry: false,
                stockImpact: 'جاهزية البضاعة للخروج النهائي مع سيارة التوصيل',
                journalEntry: null,
                nextAction: 'الفوترة النهائية',
                description: 'مرحلة الفحص البصري النهائي للتأكد من سلامة المواد الحساسة كالخزف والمغاسل قبل تسليمها للسائق.',
                steps: ['تأكيد اكتمال جميع سطور الالتقاط.', 'طباعة ملصق الشحنة وإشعار العميل بموعد الوصول.']
            },
            {
                id: 's-final-inv',
                title: 'فاتورة المبيعات الرسمية',
                stage: 'الفوترة والإغلاق',
                docType: 'INV-######',
                summary: 'صرف المخزون النهائي وتوليد القيد المحاسبي والضريبي وإقفال الطلب.',
                color: '#7c3aed',
                icon: Tickets,
                model: 'App\\Models\\Invoice',
                permission: 'sales.invoices.view',
                route: '/admin/sales/invoices',
                hasStockEffect: true,
                hasJournalEntry: true,
                stockImpact: 'خصم نهائي من available_quantity وإطلاق الحجز واستهلاك تكلفة FIFO',
                journalEntry: {
                    debit: '10201 العملاء + 50101 تكلفة المبيعات',
                    credit: '40101 الإيرادات + 20301 الضريبة + 10401 المخزون'
                },
                nextAction: 'تمت العملية بنجاح',
                description: 'الإجراء المالي والضريبي الحاسم الذي يقفل أمر البيع ويصدر الفاتورة المعتمدة.',
                steps: ['ترحيل الفاتورة الضريبية.', 'تحديث كشف حساب العميل واستحقاق المبلغ.']
            }
        ]
    },
    {
        id: 'purchases',
        title: 'دورة المشتريات والاستلام وتكلفة FIFO (Procurement & Costing)',
        description: 'تدفق التوريد من طلب الشراء الداخلي إلى أمر الشراء، فحص الجودة، الاستلام المخزني، فتح طبقة FIFO، واستحقاق المورد.',
        icon: ShoppingBag,
        nodes: [
            {
                id: 'p-req',
                title: 'طلب الشراء الداخلي',
                stage: 'تحديد الاحتياج ونقاط الطلب',
                docType: 'REQ-######',
                summary: 'طلب توريد داخلي من أمين المستودع أو الفرع لتغذية المخزون.',
                color: '#f59e0b',
                icon: Tickets,
                model: 'App\\Models\\PurchaseRequest',
                permission: 'purchases.requests.view',
                route: '/admin/purchase-requests',
                hasStockEffect: false,
                hasJournalEntry: false,
                stockImpact: 'لا يؤثر على المخزون (طلب داخلي)',
                journalEntry: null,
                nextAction: 'موافقة الإدارة',
                description: 'يولد آلياً عند وصول الصنف لنقطة إعادة الطلب ROP أو يُنشأ يدوياً من قِبل مسؤول المستودع.',
                steps: ['مراجعة الأرصدة الحالية ومعدلات البيع اليومية.', 'رفع الطلب للإدارة للموافقة على الميزانية.']
            },
            {
                id: 'p-order',
                title: 'أمر الشراء المعتمد',
                stage: 'التعاقد والتوريد',
                docType: 'PO-######',
                summary: 'إصدار أمر الشراء الرسمي للمورد وتثبيت التكلفة وموعد الوصول.',
                color: '#d97706',
                icon: ShoppingBag,
                model: 'App\\Models\\PurchaseOrder',
                permission: 'purchases.orders.view',
                route: '/admin/purchases/orders',
                hasStockEffect: false,
                hasJournalEntry: false,
                stockImpact: 'لا يغير المخزون (التزام تعاقدي مع المورد)',
                journalEntry: null,
                nextAction: 'شحن الشحنة',
                description: 'عقد الشراء الذي يحدد مواصفات الأدوات الصحية ومصنع المنشأ وتفاصيل الحاوية أو الشاحنة.',
                steps: ['إرسال أمر الشراء للمورد وانتظار إشعار الشحن.', 'متابعة تاريخ الوصول المتوقع مع شركة النقل.']
            },
            {
                id: 'p-rcv',
                title: 'فحص واستلام البضاعة',
                stage: 'المستودعات والجودة',
                docType: 'RCV-######',
                summary: 'فحص الشحنة على الرصيف، فرز السليم والتالف، وتأكيد الاستلام.',
                color: '#059669',
                icon: Box,
                model: 'App\\Models\\PurchaseReceipt',
                permission: 'purchases.receipts.view',
                route: '/admin/purchases/receipts',
                hasStockEffect: true,
                hasJournalEntry: true,
                stockImpact: 'إضافة فورية لرصيد available_quantity بالمستودع',
                journalEntry: {
                    debit: '10401 مخزون بضاعة الأدوات الصحية',
                    credit: '20101 الموردون والذمم الدائنة'
                },
                nextAction: 'فتح طبقة FIFO',
                description: 'الخطوة الفاصلة التي تزيد كميات المستودع الفعلي وتثبت التزام السداد للمورد في الدفاتر المحاسبية.',
                steps: ['معاينة السليم والتالف وإدخال محضر الفحص.', 'توليد أرقام الباركود التشغيلية للدفعة.']
            },
            {
                id: 'p-fifo',
                title: 'فتح طبقة تكلفة FIFO جديدة',
                stage: 'محرك احتساب التكلفة',
                docType: 'LAYER-FIFO',
                summary: 'تسجيل طبقة تكلفة مستقلة (الكمية × التكلفة الفعلية + مصاريف الشحن).',
                color: '#10b981',
                icon: Coin,
                model: 'App\\Services\\Inventory\\InventoryCostingService',
                permission: 'inventory.view',
                route: '/admin/inventory',
                hasStockEffect: true,
                hasJournalEntry: false,
                stockImpact: 'تسجيل طبقة في جدول inventory_cost_layers بأربعة خانات عشرية',
                journalEntry: null,
                nextAction: 'صرف الدفعة للمورد',
                description: 'تضمن طريقة الوارد أولاً يصرف أولاً تحميل مبيعات الشركة بأقدم تكلفة تاريخية بدقة بالغة.',
                steps: ['توزيع مصاريف الجمارك والشحن الإضافية على تكلفة الوحدة.', 'تخزين طبقة التكلفة في قاعدة البيانات.']
            },
            {
                id: 'p-pay',
                title: 'سداد دفعة المورد',
                stage: 'الخزينة والمدفوعات',
                docType: 'SPAY-######',
                summary: 'إصدار سند صرف بنكي للمورد وتسوية قيمة الفواتير المستحقة.',
                color: '#0f766e',
                icon: Connection,
                model: 'App\\Models\\SupplierPayment',
                permission: 'purchases.payments.view',
                route: '/admin/purchases/payments',
                hasStockEffect: false,
                hasJournalEntry: true,
                stockImpact: 'لا يؤثر على المخزون',
                journalEntry: {
                    debit: '20101 حساب المورد (تخفيض المديونية المستحقة)',
                    credit: '10102 البنك التجاري (خروج السيولة)'
                },
                nextAction: 'اكتمال الدورة',
                description: 'تسوية حساب المورد عبر حوالة بنكية أو شيك ومطابقة كشف حساب المورد الدوري.',
                steps: ['اختيار أذونات الاستلام المراد تسويتها.', 'تسجيل رقم مرجع الحوالة البنكية وإصدار السند.']
            }
        ]
    },
    {
        id: 'inventory_wms',
        title: 'معمارية المستودعات والدِلاء الثلاثة وحاويات WMS (3-Buckets & WMS)',
        description: 'الهيكل الجغرافي للمستودع، تقسيم الحاويات (Bins)، ومعادلة الأرصدة الثلاثية وحساب الكمية المتاحة للبيع الفعلي.',
        icon: Location,
        nodes: [
            {
                id: 'w-wh',
                title: 'الفروع والمستودعات الرئيسية',
                stage: 'الهيكل الجغرافي للمنشأة',
                docType: 'WH-STRUCTURE',
                summary: 'تعريف الفروع والمستودعات المركزية وأمناء المستودعات المسؤولين.',
                color: '#1e3a8a',
                icon: OfficeBuilding,
                model: 'App\\Models\\Warehouse',
                permission: 'wms.warehouses.view',
                route: '/admin/wms/warehouses',
                hasStockEffect: false,
                hasJournalEntry: false,
                stockImpact: 'تحديد النطاق المكاني للأرصدة',
                journalEntry: null,
                nextAction: 'تقسيم الحاويات',
                description: 'المظلة المكانية لجميع العمليات اللوجستية، وترتبط بها حسابات أمناء المستودعات والمناديب.',
                steps: ['تحديد اسم المستودع، المدينة، والعنوان.', 'تعيين المستودع الرئيسي الافتراضي للمنشأة.']
            },
            {
                id: 'w-bins',
                title: 'شجرة الحاويات والمواقع (Bins)',
                stage: 'المواقع التخزينية الدقيقة',
                docType: 'BIN-LOC',
                summary: 'تقسيم المستودع إلى مناطق، ممرات، رفوف، وخانات تخزين مرقمة.',
                color: '#0d9488',
                icon: Location,
                model: 'App\\Models\\Bin',
                permission: 'wms.bins.view',
                route: '/admin/wms/bins',
                hasStockEffect: true,
                hasJournalEntry: false,
                stockImpact: 'توزيع الكميات على الحاويات بدقة متناهية',
                journalEntry: null,
                nextAction: 'فرز دِلاء المخزون',
                description: 'تسمح الحاويات بمعرفة الموقع الدقيق لكل قطعة بانيو، خلاط، أو أنبوب سباكة لسرعة الالتقاط.',
                steps: ['توليد كود الحاوية بنمط (منطقة-ممر-رف-خانة).', 'طباعة ولصق باركود الحاويات على الأرفف.']
            },
            {
                id: 'w-avail',
                title: 'دلو المخزون السليم (Available)',
                stage: 'البضاعة الصالحة للتداول',
                docType: 'BUCKET-AVAIL',
                summary: 'الكميات السليمة الخالية من أي عيوب والمفحوصة تماماً على الرف.',
                color: '#16a34a',
                icon: Box,
                model: 'App\\Models\\WarehouseInventory',
                permission: 'inventory.view',
                route: '/admin/inventory',
                hasStockEffect: true,
                hasJournalEntry: false,
                stockImpact: 'العمود الأساسي لحساب إجمالي البضاعة السليمة available_quantity',
                journalEntry: null,
                nextAction: 'اقتطاع الحجز',
                description: 'الدلو الذي تُجرى عليه كافة عمليات البيع والتحويل. لا يُسمح بإدخال بضاعة تالفة فيه أبداً.',
                steps: ['تحديث الرصيد من أذونات الاستلام ومناقلات الفروع.', 'فصل أي بضاعة معيبة إلى دلو التالف فوراً.']
            },
            {
                id: 'w-hold',
                title: 'دلو التالف والمحجور (Damaged/Hold)',
                stage: 'العزل والفحص الفني',
                docType: 'BUCKET-HOLD',
                summary: 'بضاعة معزولة إما لتلفها أثناء النقل أو لكونها قيد الفحص والمعاينة.',
                color: '#dc2626',
                icon: Warning,
                model: 'App\\Models\\WarehouseInventory',
                permission: 'inventory.view',
                route: '/admin/inventory',
                hasStockEffect: true,
                hasJournalEntry: false,
                stockImpact: 'مخزون محمي ومحجوب برمجياً من شاشات الفوترة والبيع',
                journalEntry: null,
                nextAction: 'حساب الصافي القابل للبيع',
                description: 'يمنع بيع أي قطعة معطوبة للعميل عن طريق الخطأ؛ حيث تُفصل في خانات damaged وquarantined.',
                steps: ['تسجيل تقرير الفحص الفني للهالك أو المعيب.', 'إحالة الصنف للصيانة، الإتلاف، أو الرد للمصنع.']
            },
            {
                id: 'w-sellable',
                title: 'المخزون القابل للبيع (Sellable)',
                stage: 'بوابة الفوترة الصارمة',
                docType: 'NET-SELLABLE',
                summary: 'الكمية الفعلية المتاحة للبيع الفوري: السليم مطروحاً منه المحجوز.',
                color: '#2563eb',
                icon: ShoppingCart,
                model: 'WarehouseInventory::availableSql()',
                permission: 'sales.create',
                route: '/admin/sales/invoices/create',
                hasStockEffect: true,
                hasJournalEntry: false,
                stockImpact: 'المعادلة الإلزامية: max(0, available_quantity - reserved_quantity)',
                journalEntry: null,
                nextAction: 'تنفيذ الفوترة بأمان',
                description: 'المعيار الوحيد الذي يحدد هل تقبل الفاتورة البيع أم ترفض؛ مما يمنع بيع بضاعة محجوزة لعميل آخر.',
                steps: ['فحص تلقائي عند مسح الباركود في شاشة الفاتورة.', 'منع البيع وإصدار تحذير فوري عند تجاوز الرصيد المتاح.']
            }
        ]
    },
    {
        id: 'accounting',
        title: 'خريطة التوجيه المحاسبي المزدوج وقيود اليومية (Double-Entry Routing)',
        description: 'التكامل الآلي بين المستندات التشغيلية وشجرة الحسابات، مع ضمان التوازن الصفري لكل قيد يومية.',
        icon: Tickets,
        nodes: [
            {
                id: 'a-cash-sale',
                title: 'قيد مبيعات نقدية (Cash Sale)',
                stage: 'الفوترة المباشرة',
                docType: 'JE-SALE-CASH',
                summary: 'مدين: الخزينة 10101 | دائن: إيراد المبيعات 40101 + ضريبة 20301.',
                color: '#15803d',
                icon: Coin,
                model: 'App\\Services\\Accounting\\JournalService',
                permission: 'accounting.journal.view',
                route: '/admin/accounting/journal',
                hasStockEffect: false,
                hasJournalEntry: true,
                stockImpact: 'يثبت الأثر المالي لدخول النقدية',
                journalEntry: {
                    debit: '10101 الصندوق الرئيسي (الخزينة النقدية)',
                    credit: '40101 إيراد مبيعات أدوات صحية + 20301 ضريبة مخرجات'
                },
                nextAction: 'إثبات قيد التكلفة COGS',
                description: 'يتولد آلياً عند إتمام عملية بيع نقدي في شاشة الفواتير أو نقطة البيع السريعة POS.',
                steps: ['التحقق من توازن طرفي القيد بالسنت الواحد.', 'ترحيل القيد لدفتر الأستاذ العام فورياً.']
            },
            {
                id: 'a-credit-sale',
                title: 'قيد مبيعات آجلة (Credit Sale)',
                stage: 'الفوترة الآجلة',
                docType: 'JE-SALE-AR',
                summary: 'مدين: حساب العميل 10201 | دائن: إيراد المبيعات 40101 + ضريبة 20301.',
                color: '#2563eb',
                icon: UserFilled,
                model: 'App\\Services\\Accounting\\JournalService',
                permission: 'accounting.journal.view',
                route: '/admin/accounting/journal',
                hasStockEffect: false,
                hasJournalEntry: true,
                stockImpact: 'إثبات استحقاق المديونية على ذمة العميل',
                journalEntry: {
                    debit: '10201 العملاء والذمم المدينة (كشف حساب العميل)',
                    credit: '40101 إيراد مبيعات + 20301 ضريبة مخرجات'
                },
                nextAction: 'سند التحصيل',
                description: 'يثبت مديونية العميل ويزيد رصيد الذمم المدينة ويحدث أعمار الديون في تقرير Aging.',
                steps: ['فحص الحد الائتماني للعميل قبل الترحيل.', 'تسجيل القيد برقم الفاتورة كمرجع تتبعي.']
            },
            {
                id: 'a-cogs',
                title: 'قيد تكلفة البضاعة المباعة (COGS)',
                stage: 'إقفال تكلفة المخزون',
                docType: 'JE-COGS',
                summary: 'مدين: تكلفة المبيعات 50101 | دائن: مخزون المواد الصحية 10401.',
                color: '#b45309',
                icon: TrendCharts,
                model: 'App\\Services\\Accounting\\JournalService',
                permission: 'accounting.journal.view',
                route: '/admin/accounting/journal',
                hasStockEffect: false,
                hasJournalEntry: true,
                stockImpact: 'تخفيض القيمة الدفترية للمخزون في حسابات الأصول',
                journalEntry: {
                    debit: '50101 تكلفة البضاعة المباعة COGS (قائمة الدخل)',
                    credit: '10401 مخزون بضاعة الأدوات الصحية (الميزانية العمومية)'
                },
                nextAction: 'حساب مجمل الربح',
                description: 'القيد الذي يحقق مبدأ المقابلة المحاسبي بمطابقة إيراد الفاتورة مع التكلفة التاريخية لطبقات FIFO المستهلكة.',
                steps: ['سحب التكلفة الحقيقية من طبقات FIFO.', 'توليد القيد تزامناً مع الفاتورة.']
            },
            {
                id: 'a-po-rcv',
                title: 'قيد استلام المشتريات (AP)',
                stage: 'استحقاق الموردين',
                docType: 'JE-PURCHASE',
                summary: 'مدين: المخزون 10401 | دائن: حساب المورد 20101.',
                color: '#0d9488',
                icon: ShoppingBag,
                model: 'App\\Services\\Accounting\\JournalService',
                permission: 'accounting.journal.view',
                route: '/admin/accounting/journal',
                hasStockEffect: false,
                hasJournalEntry: true,
                stockImpact: 'زيادة قيمة أصول المخزون بقيمة الشحنة المستلمة',
                journalEntry: {
                    debit: '10401 مخزون بضاعة الأدوات الصحية',
                    credit: '20101 الموردون والذمم الدائنة (حساب المورد)'
                },
                nextAction: 'سداد دفعة المورد',
                description: 'إثبات دخول البضاعة لأصول الشركة والتزام سداد قيمتها للمورد في تاريخ الاستحقاق.',
                steps: ['توليد القيد آلياً عند اعتماد إذن الاستلام.', 'تحديث كشف حساب المورد.']
            },
            {
                id: 'a-credit-note',
                title: 'قيد الإشعار الدائن لمرتجع (RMA)',
                stage: 'عكس الإيراد والضريبة',
                docType: 'JE-RMA-CN',
                summary: 'مدين: مردودات مبيعات 40201 + ضريبة 20301 | دائن: العميل 10201.',
                color: '#e11d48',
                icon: Refresh,
                model: 'App\\Services\\Accounting\\JournalService',
                permission: 'accounting.journal.view',
                route: '/admin/accounting/journal',
                hasStockEffect: false,
                hasJournalEntry: true,
                stockImpact: 'تخفيض مديونية العميل وتصحيح الإقرار الضريبي',
                journalEntry: {
                    debit: '40201 مردودات ومسموحات المبيعات + 20301 ضريبة مخرجات (تعديل دائن)',
                    credit: '10201 العملاء والذمم المدينة (حساب العميل)'
                },
                nextAction: 'إعادة إدخال المخزون',
                description: 'معالجة محاسبية وقانونية دقيقة للمرتجعات تضمن عدم تضخيم المبيعات السنوية أو الإقرار الضريبي.',
                steps: ['اعتماد تقرير فحص المرتجع.', 'توليد القيد العكسي بموجب الإشعار الدائن.']
            }
        ]
    },
    {
        id: 'rma',
        title: 'دورة المرتجعات والضمان وفحص الجودة (RMA & Inspection)',
        description: 'معالجة طلبات الإرجاع، فحص الصلاحية، توجيه السليم للمستودع والتالف للحجر، وإصدار الإشعار الدائن.',
        icon: Refresh,
        nodes: [
            {
                id: 'r-req',
                title: 'استقبال طلب الإرجاع (RMA)',
                stage: 'خدمة العملاء والضمان',
                docType: 'RMA-######',
                summary: 'تسجيل طلب المرتجع وربطه برقم الفاتورة الأصلية والتحقق من الضمان.',
                color: '#e11d48',
                icon: Refresh,
                model: 'App\\Models\\ReturnOrder',
                permission: 'rma.view',
                route: '/admin/rma',
                hasStockEffect: false,
                hasJournalEntry: false,
                stockImpact: 'لا يؤثر على المخزون حتى يتم الفحص',
                journalEntry: null,
                nextAction: 'الفحص الفني',
                description: 'التحقق من أن المنتج بيع من الشركة بالفعل، وأنه يقع ضمن فترة الإرجاع النظامية أو الضمان المصنعي.',
                steps: ['البحث برقم فاتورة البيع أو الباركود.', 'اختيار الصنف وتحديد سبب الإرجاع.']
            },
            {
                id: 'r-inspect',
                title: 'فحص ومعاينة الجودة (QC)',
                stage: 'الفحص المخزني والفني',
                docType: 'QC-INSPECT',
                summary: 'معاينة الصنف وتحديد حالته الفنية: سليم، تالف، أو بحاجة لصيانة.',
                color: '#d97706',
                icon: Check,
                model: 'App\\Services\\Rma\\RmaService',
                permission: 'rma.create',
                route: '/admin/rma/create',
                hasStockEffect: true,
                hasJournalEntry: false,
                stockImpact: 'توجيه الصنف إما لدلو السليم أو التالف أو المحجور',
                journalEntry: null,
                nextAction: 'توجيه المخزون',
                description: 'بوابة الأمان التي تمنع دخول أي بضاعة معطوبة أو مستعملة إلى المخزون الجاهز للبيع.',
                steps: ['فحص العبوة والملحقات وسلامة السطح الخزفي أو الميكانيكي.', 'تحديد نتيجة الفحص واعتمادها.']
            },
            {
                id: 'r-route',
                title: 'فرز مسار البضاعة المرتجعة',
                stage: 'التوجيه المخزني',
                docType: 'RESTOCK-GATE',
                summary: 'إرجاع السليم لمستودع المبيعات أو تحويل التالف لمستودع الهوالك.',
                color: '#2563eb',
                icon: Box,
                model: 'App\\Services\\Inventory\\InventoryService',
                permission: 'inventory.manage',
                route: '/admin/stock',
                hasStockEffect: true,
                hasJournalEntry: false,
                stockImpact: 'زيادة available_quantity بالسعر الأصلي أو نقله للتالف',
                journalEntry: null,
                nextAction: 'إصدار الإشعار الدائن',
                description: 'تطبيق حركة مخزنية من نوع return مع الحفاظ على تكلفة الشراء التاريخية للبند.',
                steps: ['إعادة فتح الحاوية التخزينية في WMS.', 'تحديث بطاقة الصنف بالمستودع.']
            },
            {
                id: 'r-cn',
                title: 'إصدار الإشعار الدائن (Credit Note)',
                stage: 'الفوترة والتسوية',
                docType: 'CN-######',
                summary: 'توليد وثيقة الإشعار الدائن الرسمية وعكس الإيراد والضريبة.',
                color: '#7c3aed',
                icon: Coin,
                model: 'App\\Models\\CreditNote',
                permission: 'rma.credit_notes.view',
                route: '/admin/rma/credit-notes',
                hasStockEffect: false,
                hasJournalEntry: true,
                stockImpact: 'تخفيض الإيراد والضريبة وقيد المبلغ للعميل',
                journalEntry: {
                    debit: '40201 مردودات مبيعات + 20301 ضريبة مخرجات',
                    credit: '10201 حساب العميل'
                },
                nextAction: 'التسوية المالية',
                description: 'الوثيقة المحاسبية الرسمية التي تلغي الفاتورة أو جزءاً منها وتمنح العميل حقه المالي قانونياً.',
                steps: ['توليد رقم الإشعار الدائن المسلسل.', 'طباعة الإشعار وتوقيعه.']
            },
            {
                id: 'r-settle',
                title: 'التسوية المالية النهائية',
                stage: 'التحصيل والمطابقة',
                docType: 'SETTLE-FINAL',
                summary: 'إبقاء المبلغ كرصيد دائن للعميل أو استرداد نقدي فوري له.',
                color: '#059669',
                icon: Connection,
                model: 'App\\Models\\Customer',
                permission: 'sales.payments.view',
                route: '/admin/sales/customers',
                hasStockEffect: false,
                hasJournalEntry: true,
                stockImpact: 'لا يؤثر على المخزون',
                journalEntry: {
                    debit: '10201 حساب العميل (تخفيض الرصيد الدائن)',
                    credit: '10101 الخزينة (في حال الاسترداد النقدي الفوري)'
                },
                nextAction: 'إغلاق الملف بنجاح',
                description: 'إقفال دورة الإرجاع برضى العميل، إما بتسليمه البديل أو النقود أو حفظ الرصيد لفاتورة لاحقة.',
                steps: ['اختيار طريقة التسوية المفضلة للعميل.', 'إغلاق تذكرة الإرجاع RMA.']
            }
        ]
    }
];

const currentDiagram = computed(() => {
    return diagramsList.find(d => d.id === selectedDiagramId.value) || diagramsList[0];
});

const selectDiagram = (id) => {
    selectedDiagramId.value = id;
    const diag = diagramsList.find(d => d.id === id);
    if (diag && diag.nodes.length) {
        selectedDiagramNode.value = diag.nodes[0];
    }
};

const selectNode = (node) => {
    selectedDiagramNode.value = node;
};

// Initialize first node of master diagram on load
selectedDiagramNode.value = diagramsList[0].nodes[0];


const simStock = ref({
    available: 120,
    damaged: 5,
    quarantined: 3,
    reserved: 40
});

const simTotalStock = computed(() => {
    return simStock.value.available + simStock.value.damaged + simStock.value.quarantined;
});

const simSellableStock = computed(() => {
    return Math.max(0, simStock.value.available - simStock.value.reserved);
});

const simSellablePercent = computed(() => {
    if (simTotalStock.value <= 0) return 0;
    return Math.round((simSellableStock.value / simTotalStock.value) * 100);
});

const simReservedPercent = computed(() => {
    if (simTotalStock.value <= 0) return 0;
    return Math.round((simStock.value.reserved / simTotalStock.value) * 100);
});

const simDamagedPercent = computed(() => {
    if (simTotalStock.value <= 0) return 0;
    return Math.round((simStock.value.damaged / simTotalStock.value) * 100);
});

const simQuarantinedPercent = computed(() => {
    if (simTotalStock.value <= 0) return 0;
    return Math.max(0, 100 - (simSellablePercent.value + simReservedPercent.value + simDamagedPercent.value));
});

// Interactive Simulator 2: FIFO Cost Layers State
const simFifo = ref({
    layers: [
        { quantity: 30, cost: 100 },
        { quantity: 50, cost: 120 },
        { quantity: 20, cost: 135 },
    ],
    sellQty: 45
});

const simFifoTotalQty = computed(() => {
    return simFifo.value.layers.reduce((sum, l) => sum + l.quantity, 0);
});

const simFifoResult = computed(() => {
    let remainingToSell = simFifo.value.sellQty;
    let totalCogs = 0;
    const consumedSteps = [];
    const simulatedLayers = simFifo.value.layers.map(l => ({ ...l }));

    for (let i = 0; i < simulatedLayers.length; i++) {
        if (remainingToSell <= 0) break;
        const layer = simulatedLayers[i];
        const taken = Math.min(layer.quantity, remainingToSell);
        if (taken > 0) {
            const stepCost = taken * layer.cost;
            totalCogs += stepCost;
            layer.quantity -= taken;
            remainingToSell -= taken;
            consumedSteps.push({
                layerIndex: i,
                qty: taken,
                cost: layer.cost,
                totalCost: stepCost
            });
        }
    }

    const remainingStockValue = simulatedLayers.reduce((sum, l) => sum + (l.quantity * l.cost), 0);
    const avgCostPerUnit = simFifo.value.sellQty > 0 ? (totalCogs / simFifo.value.sellQty) : 0;

    return {
        consumedSteps,
        totalCogs,
        avgCostPerUnit,
        remainingStockValue
    };
});

// Interactive Simulator 3: ROP State
const simRop = ref({
    dailySales: 15,
    leadTimeDays: 7,
    safetyStock: 30
});

const simCalculatedRop = computed(() => {
    return Math.ceil(simRop.value.dailySales * simRop.value.leadTimeDays + simRop.value.safetyStock);
});

// Interactive Simulator 4: Double-Entry Journal State
const selectedJournalOp = ref('sale_cash_vat');

const journalOperations = [
    {
        id: 'sale_cash_vat',
        name: 'فاتورة مبيعات نقدية مع ضريبة القيمة المضافة',
        description: 'تتولد عند إصدار فاتورة بيع نقدية مدفوعة بالكامل في نقطة البيع أو شاشة الفواتير.',
        lines: [
            { accountCode: '10101', accountName: 'الصندوق الرئيسي / الخزينة', debit: 1150, credit: 0, note: 'قبض القيمة النقدية الإجمالية للفاتورة' },
            { accountCode: '40101', accountName: 'إيراد مبيعات الأدوات الصحية', debit: 0, credit: 1000, note: 'قيمة البضاعة المباعة قبل الضريبة' },
            { accountCode: '20301', accountName: 'ضريبة القيمة المضافة المحصلة (مخرجات)', debit: 0, credit: 150, note: 'ضريبة 15% مستحقة للهيئة' },
        ]
    },
    {
        id: 'sale_credit_vat',
        name: 'فاتورة مبيعات آجلة لعميل تجاري مع ضريبة',
        description: 'تتولد عند ترحيل فاتورة بيع آجل لعميل مسجل، مع التأثير على كشف حسابه ورصيده.',
        lines: [
            { accountCode: '10201', accountName: 'العملاء والذمم المدينة (حساب العميل)', debit: 2300, credit: 0, note: 'إثبات مديونية العميل بقيمة الفاتورة' },
            { accountCode: '40101', accountName: 'إيراد مبيعات الأدوات الصحية', debit: 0, credit: 2000, note: 'قيمة المبيعات المستحقة' },
            { accountCode: '20301', accountName: 'ضريبة القيمة المضافة المحصلة (مخرجات)', debit: 0, credit: 300, note: 'ضريبة مبيعات مستحقة للهيئة' },
        ]
    },
    {
        id: 'purchase_receipt',
        name: 'إذن استلام بضاعة مشتريات من مورد في المستودع',
        description: 'يتولد لحظة فحص وتأكيد استلام شحنة مشتريات وتخزينها في مستودعات الشركة.',
        lines: [
            { accountCode: '10401', accountName: 'مخزون بضاعة الأدوات الصحية', debit: 5000, credit: 0, note: 'إدخال البضاعة للمخزن وفتح طبقة تكلفة FIFO' },
            { accountCode: '20101', accountName: 'الموردون والذمم الدائنة (حساب المورد)', debit: 0, credit: 5000, note: 'استحقاق قيمة الشحنة للمورد' },
        ]
    },
    {
        id: 'supplier_payment',
        name: 'سند صرف وسداد دفعة لمورد عبر تحويل بنكي',
        description: 'يتولد عند سداد دفعة مالية للمورد تخصم من رصيد حسابه وتوثق الحوالة البنكية.',
        lines: [
            { accountCode: '20101', accountName: 'الموردون والذمم الدائنة (حساب المورد)', debit: 3500, credit: 0, note: 'تخفيض مديونية المورد بموجب الحوالة' },
            { accountCode: '10102', accountName: 'البنك التجاري (الحساب الجاري)', debit: 0, credit: 3500, note: 'خروج السيولة من الحساب البنكي' },
        ]
    },
    {
        id: 'rma_credit_note',
        name: 'إشعار دائن لمرتجع مبيعات (Credit Note)',
        description: 'يتولد عند قبول مرتجع من العميل وإصدار إشعار دائن لعكس الإيراد والضريبة.',
        lines: [
            { accountCode: '40201', accountName: 'مردودات ومسموحات المبيعات', debit: 800, credit: 0, note: 'إثبات قيمة البضاعة المرتجعة' },
            { accountCode: '20301', accountName: 'ضريبة القيمة المضافة المحصلة (تعديل دائن)', debit: 120, credit: 0, note: 'استرداد الضريبة المرتبطة بالمرتجع' },
            { accountCode: '10201', accountName: 'العملاء والذمم المدينة (حساب العميل)', debit: 0, credit: 920, note: 'تخفيض رصيد مديونية العميل' },
        ]
    },
    {
        id: 'payroll_posting',
        name: 'ترحيل مسير الرواتب الشهري للموظفين',
        description: 'يتولد عند اعتماد مسير الرواتب الشهري واحتساب البدلات والاستقطاعات.',
        lines: [
            { accountCode: '50101', accountName: 'مصروف رواتب وأجور الموظفين', debit: 15000, credit: 0, note: 'إجمالي استحقاق الرواتب الأساسية' },
            { accountCode: '50102', accountName: 'مصروف بدلات وعمولات المبيعات', debit: 3500, credit: 0, note: 'عمولات المبيعات وبدل السكن والنقل' },
            { accountCode: '20202', accountName: 'استقطاعات وتأمينات مستحقة', debit: 0, credit: 1500, note: 'استقطاعات التأمينات الاجتماعية والغياب' },
            { accountCode: '20201', accountName: 'أمانات الرواتب المستحقة للصرف', debit: 0, credit: 17000, note: 'صافي الرواتب الجاهز للتحويل البنكي' },
        ]
    }
];

const currentJournalOp = computed(() => {
    return journalOperations.find(op => op.id === selectedJournalOp.value) || journalOperations[0];
});

const currentJournalOpTotalDebit = computed(() => {
    return currentJournalOp.value.lines.reduce((sum, l) => sum + (l.debit || 0), 0);
});

const currentJournalOpTotalCredit = computed(() => {
    return currentJournalOp.value.lines.reduce((sum, l) => sum + (l.credit || 0), 0);
});

// Master Rules Categories
const masterRulesCategories = [
    {
        title: 'قواعد حركة المخزون وتحديد الصلاحية',
        icon: Box,
        color: '#2563eb',
        rules: [
            {
                code: 'INV-01',
                title: 'معادلة المخزون القابل للبيع الفعلي',
                severity: 'critical',
                severityText: 'إلزامية صارمة',
                description: 'لا يجوز لأي فاتورة أو أمر بيع سحب بضاعة تتجاوز رصيد المخزون السليم مطروحاً منه الحجوزات القائمة.',
                formula: 'sellable_stock = max(0, available_quantity - reserved_quantity)',
                consequence: 'يقوم النظام برفض العملية وعرض استثناء: الكمية المطلوبة تتجاوز المخزون المتاح.'
            },
            {
                code: 'INV-02',
                title: 'فريدة مفتاح الحركة المخزنية (Idempotency)',
                severity: 'critical',
                severityText: 'إلزامية صارمة',
                description: 'كل حركة إدخال أو إخراج تحمل مفتاح تفرد movement_key لمنع تكرار الحركة عند بطء الشبكة.',
                formula: 'movement_key = sha256(ref + type + timestamp + payload)',
                consequence: 'إذا أُعيد إرسال نفس الطلب، يعيد النظام الحركة السابقة فوراً دون تكرار الخصم.'
            },
            {
                code: 'INV-03',
                title: 'التسوية الجردية كبيان حقيقة مطلق',
                severity: 'warning',
                severityText: 'قاعدة إدارية',
                description: 'أوامر التسوية الناتجة عن الجرد الفعلي للمستودع تفرض allow_negative=true لأن الجرد توثيق لواقع الرف الفعلي ولا يجوز منعه برمجياً.',
                formula: 'variance = actual_count - system_count',
                consequence: 'تُسجل الحركة فوراً مع توليد قيد خسارة أو أرباح فروقات جرد المخزون.'
            }
        ]
    },
    {
        title: 'قواعد التسعير والتكلفة FIFO',
        icon: Coin,
        color: '#059669',
        rules: [
            {
                code: 'COST-01',
                title: 'تحديد تكلفة البضاعة بطبقات FIFO التاريخية',
                severity: 'critical',
                severityText: 'إلزامية محاسبية',
                description: 'تكلفة الوحدة المباعة في الفاتورة تُستبدل دائماً بتكلفة الطبقة الواردة الأقدم، ولا تعتمد على السعر المقترح في بطاقة الصنف.',
                formula: 'COGS = ∑ (Layer_Units_Consumed × Layer_Actual_Unit_Cost)',
                consequence: 'تضمن صحة هامش الربح المحاسبي ومنع التضخيم أو الانتقاص من الأرباح.'
            },
            {
                code: 'COST-02',
                title: 'إعادة تكلفة المرتجعات بالسعر الأصلي',
                severity: 'warning',
                severityText: 'قاعدة محاسبية',
                description: 'عند إرجاع بضاعة من عميل، تضاف البضاعة إلى المخزون بنفس سعر التكلفة التي بيعت بها في فاتورتها الأصلية.',
                consequence: 'منع تشويه طبقات تكلفة FIFO عند حدوث مرتجعات متعددة.'
            }
        ]
    },
    {
        title: 'قواعد المحاسبة وتوازن القيود',
        icon: Tickets,
        color: '#7c3aed',
        rules: [
            {
                code: 'ACC-01',
                title: 'التوازن الصفري الإلزامي لقيد اليومية',
                severity: 'critical',
                severityText: 'إلزامية صارمة',
                description: 'يرفض النظام قطعياً ترحيل أو حفظ أي قيد يومية لا يتساوى فيه إجمالي الطرف المدين مع إجمالي الطرف الدائن.',
                formula: '∑ Debit - ∑ Credit = 0.00',
                consequence: 'يمنع الترحيل برمجياً وظهور تنبيه خطأ في توازن القيد.'
            },
            {
                code: 'ACC-02',
                title: 'قفل الفترات المحاسبية المنتهية',
                severity: 'critical',
                severityText: 'حماية جنائية',
                description: 'يُقفل الشهر المالي أو السنة المنتهية لمنع أي تعديل أو إضافة قيود بأثر رجعي لحماية البيانات المدققة.',
                consequence: 'يمنع النظام الحفظ لأي معاملة يقع تاريخها ضمن الفترة المقفلة إلا بإذن Master Admin.'
            }
        ]
    }
];

// Master Documentation Sections Database
const sectionsData = [
    {
        id: 'dashboard-module',
        title: 'لوحة التحكم والمؤشرات التنفيذية (Dashboard & KPIs)',
        shortTitle: 'لوحة التحكم',
        category: 'الرئيسية والمتابعة',
        theme: 'blue',
        color: '#2563eb',
        icon: Odometer,
        primaryRoute: '/admin/dashboard',
        roles: ['admin', 'sales', 'accounting', 'inventory', 'hr'],
        description: 'شاشة الهبوط الإدارية الذكية التي تقدم نبضاً حياً وفورياً لأداء المنشأة المالي والمخزني والبيعي مع دعم التخصيص التلقائي حسب دور المستخدم.',
        screens: [
            { name: 'لوحة التحكم المركزية', path: '/admin/dashboard' },
            { name: 'لوحة متابعة المندوب الميداني', path: '/admin/dashboard (نمط المندوب)' },
        ],
        deepNote: 'يقوم النظام بتبديل لوحة التحكم تلقائياً بين اللوحة الإدارية العامة ولوحة المندوب الميداني (RepDashboard) بمجرد رصد أن حساب المستخدم مخصص للمبيعات الميدانية.',
        items: [
            {
                name: 'المؤشرات الحيوية المركزية (Executive KPIs)',
                description: 'عرض فوري لصافي المبيعات، إجمالي التحصيلات، المديونيات المستحقة، الأرباح التقديرية، وحجم المخزون مع مؤشرات مقارنة بالشهر السابق.',
                steps: [
                    'تسجيل الدخول إلى لوحة التحكم واستعراض بطاقات الأداء العلوية.',
                    'الضغط على أي بطاقة KPI للانتقال المباشر إلى الشاشة التابعة (فواتير، دفعات، أو مخزون).',
                    'استخدام زر التحديث اليدوي لمزامنة البيانات في حال وجود حركة كثيفة.'
                ],
                rules: [
                    'جميع الأرقام المعروضة تترجم تلقائياً إلى العملة الأساسية للمنشأة مع وسم العملة.',
                    'المستخدمون غير الإداريين تقتصر بياناتهم على نطاق فروعهم أو عملائهم المخصصين.'
                ],
                permissions: ['dashboard.view']
            },
            {
                name: 'نافذة الدفع السريع (Quick Payment Modal)',
                description: 'زر إجراء سريع في رأس لوحة التحكم يتيح تسجيل سند قبض أو دفعة مالية فورية لعميل دون مغادرة الشاشة الرئيسية.',
                steps: [
                    'النقر على زر "دفعة سريعة" في أعلى يسار لوحة التحكم.',
                    'تحديد العميل، طريقة الدفع (نقدي/بنكي)، المبلغ، والعملة.',
                    'حفظ السند، ليتم تحديث الأرصدة والمؤشرات فوراً.'
                ],
                rules: [
                    'الدفعة تخصم فوراً من رصيد العميل وتنشئ سند قبض رسمي في وحدة المبيعات وقيد يومية في المحاسبة.'
                ],
                permissions: ['sales.payments.create']
            }
        ]
    },
    {
        id: 'content-catalog-module',
        title: 'إدارة الكتالوج، المنتجات، والتسعير (Catalog & Products)',
        shortTitle: 'المنتجات والكتالوج',
        category: 'المحتوى والكتالوج',
        theme: 'indigo',
        color: '#4f46e5',
        icon: Box,
        primaryRoute: '/admin/products',
        roles: ['admin', 'sales', 'inventory'],
        description: 'إدارة متكاملة لكتالوج المنتجات الصحية، التصنيفات الشجرية، المتغيرات، وحدات القياس، الدفعات، وأسعار البيع والكتالوج الرقمي.',
        screens: [
            { name: 'قائمة المنتجات', path: '/admin/products' },
            { name: 'إضافة منتج جديد', path: '/admin/products/create' },
            { name: 'شجرة الفئات والتصنيفات', path: '/admin/categories' },
            { name: 'وحدات المنتجات والتحويلات', path: '/admin/products/units' },
            { name: 'الكتالوج وعرض الأسعار الرقمي', path: '/admin/products/price-offer' },
            { name: 'العروض الترويجية الخاصة', path: '/admin/special-offers' },
        ],
        deepNote: 'يدعم الكتالوج ثلاث مستويات دقة: منتج قياسي ← متغيرات (Variants: ألوان/مقاسات) ← تتبع الدفعات وتواريخ الصلاحية والأرقام التسلسلية (Serial Numbers).',
        items: [
            {
                name: 'إدارة المنتجات البسيطة والمركبة (Products & Bundles)',
                description: 'تعريف بطاقة الصنف متضمنة: الاسم والوصف ثنائي اللغة، الباركود، الفئة، التكلفة القياسية، سعر البيع، نسبة الضريبة، والحد الأدنى للمخزون.',
                steps: [
                    'الدخول إلى "المحتوى" ثم "المنتجات" والضغط على "إضافة منتج".',
                    'ملء البيانات الأساسية، اختيار المستودع المبدئي، وتحديد خيارات الضريبة.',
                    'في حال المنتجات المركبة: ربط المكونات الفرعية والكميات اللازمة لتكوين المنتج التجميعي.'
                ],
                rules: [
                    'لا يمكن تكرار الباركود أو رمز المنتج (SKU) على مستوى النظام.',
                    'المنتج المركب يُخصم مخزون مكوناته الفرعية آلياً عند بيعه وتأكيد الفاتورة.'
                ],
                permissions: ['products.view', 'products.create', 'products.edit']
            },
            {
                name: 'وحدات القياس ومُعاملات التحويل (Product Units)',
                description: 'إمكانية بيع وشراء المنتج بوحدات متعددة (قطعة، حبة، كرتونة، باليت) مع تحديد مُعامل التحويل الحسابي ومُعامل السعر.',
                steps: [
                    'التوجه إلى "وحدات المنتجات" وربط وحدة إضافية بالمنتج الأساسي.',
                    'تحديد معامل التحويل (مثلاً: 1 كرتونة = 24 قطعة) وسعر البيع الخاص بالوحدة.'
                ],
                rules: [
                    'تتم ترجمة كافة حركات المخزون في التقارير المركزية إلى الوحدة الأساسية للمنتج.'
                ],
                permissions: ['products.units.view']
            },
            {
                name: 'الكتالوج الرقمي وعروض الأسعار التفاعلية (Catalog & Price Offers)',
                description: 'توليد كتالوج منتجات مصور تفاعلي، وتصدير عروض أسعار PDF رسمية للعملاء بأسلوب احترافي متضمنة الشعار ومعلومات التواصل.',
                steps: [
                    'تحديد المنتجات أو الفئة المراد إدراجها في الكتالوج.',
                    'اختيار نوع التسعير (جملة/تجزئة) وتوليد رابط أو ملف PDF فوري.'
                ],
                rules: [
                    'الأسعار في الكتالوج تحتسب بالعملة المحددة مع مراعاة العروض الخاصة السارية.'
                ],
                permissions: ['products.catalog.view']
            }
        ]
    },
    {
        id: 'sales-module',
        title: 'إدارة المبيعات، الفواتير، ونقاط البيع (Sales & POS)',
        shortTitle: 'المبيعات ونقاط البيع',
        category: 'المعاملات التجارية',
        theme: 'emerald',
        color: '#059669',
        icon: ShoppingCart,
        primaryRoute: '/admin/sales/invoices',
        roles: ['admin', 'sales', 'accounting'],
        description: 'شريان النظام التجاري: إدارة شاملة لفواتير البيع، أوامر المبيعات، عروض الأسعار، نقطة البيع السريعة، حسابات العملاء، وسندات القبض.',
        screens: [
            { name: 'نظرة عامة على المبيعات', path: '/admin/sales' },
            { name: 'فواتير المبيعات', path: '/admin/sales/invoices' },
            { name: 'إنشاء فاتورة مبيعات', path: '/admin/sales/invoices/create' },
            { name: 'نقطة البيع السريعة POS', path: '/admin/pos' },
            { name: 'أوامر البيع', path: '/admin/sales/sales-orders' },
            { name: 'عروض الأسعار للعملاء', path: '/admin/sales/quotes' },
            { name: 'سجل العملاء التجاريين', path: '/admin/sales/customers' },
            { name: 'سندات قبض المبيعات', path: '/admin/sales/payments' },
            { name: 'تقارير المبيعات الاحترافية', path: '/admin/sales/reports' }
        ],
        deepNote: 'يدعم النظام "وضع التركيز" (Focus Mode) في شاشة الفاتورة لسرعة إدخال البنود والباركود، مع توجيه ذكي للمخزون يحدد أفضل مستودع لتسليم الطلبية.',
        items: [
            {
                name: 'إنشاء وإدارة فواتير المبيعات (Sales Invoices Lifecycle)',
                description: 'إصدار فواتير بيع معتمدة تدعم العملاء المسجلين والعملاء العابرين (Walk-in)، خصومات الأصناف، ضريبة القيمة المضافة، وطرق سداد متعددة.',
                steps: [
                    'الانتقال إلى "إنشاء فاتورة" واختيار العميل أو نمط "عميل عابر".',
                    'البحث عن المنتجات بالاسم أو قراءة الباركود، وتحديد الكمية والخصم إن وجد.',
                    'اختيار مستودع الصرف، ومراجعة المجاميع والضريبة.',
                    'حفظ كمسودة أو ترحيل واعتماد فوري مع خيار طباعة الإيصال الحراري أو القياسي.'
                ],
                rules: [
                    'لا يمكن اعتماد فاتورة تتجاوز رصيد المخزون المتاح ما لم يكن خيار البيع بالسالب مفعلاً بإذن إداري.',
                    'عند الاعتماد: يتم خصم الكمية من المخزون وتوليد قيد يومية فوري (مدين: العملاء/الصندوق، دائن: المبيعات وضريبة المخرجات).',
                    'يتم قفل الفاتورة المرحّلة ولا يمكن تعديلها إلا عبر إجراء إرجاع رسمي (RMA).'
                ],
                permissions: ['sales.view', 'sales.create', 'sales.invoices.view']
            },
            {
                name: 'نقطة البيع السريعة (Point of Sale - POS)',
                description: 'واجهة فائقة السرعة مخصصة لصالات العرض والبيع المباشر، متوافقة مع شاشات اللمس وقارئات الباركود وطابعات الإيصالات النقدية.',
                steps: [
                    'فتح شاشة POS من القائمة أو شريط الوصول السريع.',
                    'مسح الباركود، إدخال المبلغ المدفوع من العميل، وسيقوم النظام باحتساب المتبقي تلقائياً.',
                    'الضغط على F2 أو زر إنهاء العملية لطباعة الإيصال فوراً وفتح درج النقدية.'
                ],
                rules: [
                    'تُسجل مبيعات POS مباشرة على الصندوق النقدي الخاص بنقطة البيع مع ربطها بجلسة الكاشير الحالية.'
                ],
                permissions: ['pos.view', 'sales.create']
            },
            {
                name: 'عروض الأسعار وأوامر البيع (Quotes & Sales Orders)',
                description: 'إصدار عروض أسعار للعملاء بفترة صلاحية محددة، ثم تحويلها بنقرة واحدة إلى أمر بيع (Sales Order) ثم إلى فاتورة نهائية.',
                steps: [
                    'إنشاء عرض سعر وإرساله للعميل عبر PDF أو البريد الإلكتروني.',
                    'عند موافقة العميل: الضغط على "تحويل إلى أمر بيع" ليقوم النظام بحجز المخزون المطلوب تلقائياً لمنع بيعه لعميل آخر.'
                ],
                rules: [
                    'حجز المخزون في أمر البيع ينقل الكمية إلى خانة <code>reserved_quantity</code> دون خصمها من الرف حتى يتم الشحن الفعلي.'
                ],
                permissions: ['sales.quotes.view', 'sales.orders.view']
            },
            {
                name: 'سندات القبض وتسوية حسابات العملاء (Customer Payments)',
                description: 'تسجيل الدفعات النقدية والتحويلات البنكية والشيكات مع إمكانية ربط الدفعة بفاتورة محددة أو تسجيلها كرصيد دائن على حساب العميل.',
                steps: [
                    'الدخول إلى "المدفوعات" واختيار العميل.',
                    'تحديد المبلغ وطريقة القبض، ثم توزيع المبلغ على الفواتير المفتوحة الأقدم فالأحدث.'
                ],
                rules: [
                    'يتم تحديث رصيد العميل فوراً وتوليد قيد محاسبي (مدين: الصندوق/البنك، دائن: حساب العميل).',
                    'دعم فحص الحد الائتماني (Credit Limit) وتنبيه الإدارة عند تجاوزه.'
                ],
                permissions: ['sales.payments.view']
            }
        ]
    },
    {
        id: 'rma-returns-module',
        title: 'إدارة المرتجعات والضمان والإشعارات الدائنة (RMA)',
        shortTitle: 'المرتجعات والضمان',
        category: 'المعاملات التجارية',
        theme: 'rose',
        color: '#e11d48',
        icon: Refresh,
        primaryRoute: '/admin/rma',
        roles: ['admin', 'sales', 'inventory', 'accounting'],
        description: 'دورة محكمة لإدارة مرتجعات العملاء، فحص ومعاينة البضاعة المرتجعة، إصدار الإشعارات الدائنة، وإعادة الصالح للمخزون أو إحالة التالف للصيانة.',
        screens: [
            { name: 'نظرة عامة على المرتجعات', path: '/admin/rma' },
            { name: 'إنشاء طلب إرجاع RMA', path: '/admin/rma/create' },
            { name: 'الإشعارات الدائنة (Credit Notes)', path: '/admin/rma/credit-notes' }
        ],
        deepNote: 'لا يعود المرتجع إلى المخزون القابل للبيع فوراً، بل يدخل مرحلة فحص الجودة لتحديد إن كان صالحاً للبيع أو تالفاً يحتاج عزل.',
        items: [
            {
                name: 'دورة طلب الإرجاع (RMA Request Lifecycle)',
                description: 'استقبال طلب المرتجع من العميل بالاستناد إلى رقم الفاتورة الأصلية لضمان صحة الأسعار وتاريخ الشراء.',
                steps: [
                    'البحث برقم فاتورة البيع الأصلية واختيار الصنف والكمية المراد إرجاعها.',
                    'تحديد سبب الإرجاع (عيب مصنعي، خطأ في المقاس، استبدال).',
                    'معاينة الصنف: توجيه الصنف السليم إلى المستودع، أو التالف إلى مستودع الهوالك/الحجر.'
                ],
                rules: [
                    'لا يسمح بإرجاع كمية تتجاوز الكمية المباعة أصلاً في الفاتورة.',
                    'يتم استرجاع تكلفة البضاعة في المخزون وفق طبقة التكلفة التاريخية الأصلية.'
                ],
                permissions: ['rma.view', 'rma.create']
            },
            {
                name: 'إصدار الإشعارات الدائنة (Credit Notes)',
                description: 'توليد إشعار دائن رسمي للعميل بقيمة المرتجع مع خيار إعادة المبلغ نقداً أو قيده كرصيد دائن في حسابه يُخصم من مشترياته القادمة.',
                steps: [
                    'اعتماد تقرير فحص المرتجع وتوليد الإشعار الدائن.',
                    'اختيار آلية التسوية: رصيد مستقبلي للعميل أو استرداد نقدي فوري.'
                ],
                rules: [
                    'يتم عكس قيد المبيعات والضريبة آلياً بموجب الإشعار الدائن لحفظ الدقة الضريبية.'
                ],
                permissions: ['rma.credit_notes.view']
            }
        ]
    },
    {
        id: 'purchases-module',
        title: 'إدارة المشتريات، الموردين، والاستلامات (Purchases & Suppliers)',
        shortTitle: 'المشتريات والموردين',
        category: 'سلسلة التوريد',
        theme: 'amber',
        color: '#d97706',
        icon: ShoppingBag,
        primaryRoute: '/admin/purchases',
        roles: ['admin', 'inventory', 'accounting'],
        description: 'إدارة عمليات الشراء من المصانع والموردين، طلبات الشراء الداخلية، أوامر الشراء الرسمية، أذونات الاستلام المخزني، وسندات صرف دفعات الموردين.',
        screens: [
            { name: 'نظرة عامة على المشتريات', path: '/admin/purchases' },
            { name: 'سجل الموردين', path: '/admin/purchases/suppliers' },
            { name: 'أوامر الشراء (Purchase Orders)', path: '/admin/purchases/orders' },
            { name: 'أذونات الاستلام المخزني (Receipts)', path: '/admin/purchases/receipts' },
            { name: 'سندات صرف الموردين (Payments)', path: '/admin/purchases/payments' },
            { name: 'مرتجعات الشراء للموردين', path: '/admin/purchases/returns' },
            { name: 'تقارير المشتريات التفصيلية', path: '/admin/purchases/report' },
            { name: 'طلبات الشراء الداخلية (Purchase Requests)', path: '/admin/purchase-requests' }
        ],
        deepNote: 'الاستلام المخزني هو النقطة الزمنية التي تفتح فيها طبقة تكلفة FIFO جديدة (Inventory Cost Layer) وتزيد رصيد المستودع الفعلي.',
        items: [
            {
                name: 'أوامر الشراء وعروض الموردين (Purchase Orders)',
                description: 'إنشاء ومتابعة أوامر الشراء بدورة حياة متسلسلة: مسودة (Pending) ← معتمد (Confirmed) ← قيد الشحن (Ordered) ← مستلم (Received).',
                steps: [
                    'اختيار المورد وتحديد المستودع المستلم وشروط الدفع والعملة.',
                    'إضافة الأصناف، الكميات المتفق عليها، وتكلفة الشراء التقديرية.',
                    'إرسال أمر الشراء للمورد وانتظار الشحنة.'
                ],
                rules: [
                    'أمر الشراء لا يغير أرصدة المخزون ولا ينشئ قيداً مالياً، هو وثيقة تعاقدية فقط.'
                ],
                permissions: ['purchases.view', 'purchases.orders.view']
            },
            {
                name: 'الاستلام المخزني وتحديث طبقات التكلفة (Goods Receipts)',
                description: 'توثيق وصول البضائع إلى المستودع الفعلي وفحص مطابقتها لأمر الشراء وتوزيعها على مواقع التخزين (Bins).',
                steps: [
                    'فتح أمر الشراء المراد استلامه والضغط على "استلام بضاعة".',
                    'إدخال الكمية المستلمة فعلياً، ورقم تشغيلة الدفعة (Batch) وتاريخ الانتهاء إن وجد.',
                    'تأكيد الاستلام لتغذية المخزون فوراً.'
                ],
                rules: [
                    'يتم إنشاء حركة مخزون من نوع <code>in</code> وفتح طبقة FIFO بسعر التكلفة الفعلي متضمناً مصاريف الشحن والجمارك إن وزعت عليها.',
                    'ينشأ قيد استحقاق المورد (مدين: المخزون/بضاعة بالطريق، دائن: الموردون).'
                ],
                permissions: ['purchases.receipts.view']
            },
            {
                name: 'دفعات الموردين وتسوية كشوف الحسابات (Supplier Payments)',
                description: 'إصدار سندات صرف للموردين نقداً أو عبر حوالات بنكية ومطابقة فواتير الشراء المستحقة.',
                steps: [
                    'الدخول إلى "دفعات الموردين"، اختيار المورد، والمبلغ المسدد.',
                    'ربط الدفعة بأذونات الاستلام المعلقة لتحديث صافي المستحق.'
                ],
                rules: [
                    'توليد قيد يومية فوري (مدين: حساب المورد، دائن: البنك أو الصندوق).'
                ],
                permissions: ['purchases.payments.view']
            }
        ]
    },
    {
        id: 'inventory-module',
        title: 'إدارة المخزون، الحركات، وتكلفة FIFO (Inventory & Costing)',
        shortTitle: 'المخزون والتكلفة',
        category: 'المخزون واللوجستيات',
        theme: 'orange',
        color: '#ea580c',
        icon: Box,
        primaryRoute: '/admin/inventory',
        roles: ['admin', 'inventory', 'accounting'],
        description: 'المحرك المركزي لإدارة أرصدة الأصناف، سجل التدقيق لكافة حركات الدخول والخروج، التسويات الجردية، وتكلفة الوارد أولاً يصرف أولاً (FIFO).',
        screens: [
            { name: 'نظرة عامة على المخزون', path: '/admin/inventory' },
            { name: 'إدارة المخزون والجرد', path: '/admin/stock' },
            { name: 'سجل حركات المخزون (Movements)', path: '/admin/inventory/movements' }
        ],
        deepNote: 'تعتمد جميع حركات المخزون على خدمة مركزية واحدة (InventoryService) تضمن عدم وقوع أي بيع تكراري أو تناقض في الأرصدة عبر Transactions وLocks ذكية.',
        items: [
            {
                name: 'معادلة الدِلاء الثلاثة وتتبع الصلاحية للأصناف',
                description: 'تقسيم رصيد كل صنف في كل مستودع إلى دلاء واضحة المعالم تمنع خلط الصالح للبيع بالتالف أو المحجور.',
                steps: [
                    'استعراض جدول المخزون لرؤية تفصيل: الإجمالي على الرف، المتاح، المحجوز، التالف، والمحجور.',
                    'استخدام فلترة حسب المستودع أو فئة المنتجات لمتابعة نقطة إعادة الطلب (Reorder Point).'
                ],
                rules: [
                    'المعادلة المرجعية الإلزامية: <code>المتاح للبيع = max(0, الكمية السليمة - الكمية المحجوزة)</code>.',
                    'الحجز لأوامر البيع لا ينقل البضاعة من مكانها بل يقفلها برمجياً فقط لمنع بيعها لعميل آخر.'
                ],
                permissions: ['inventory.view']
            },
            {
                name: 'حركات المخزون المركزية (Stock Movements Audit Trail)',
                description: 'سجل تدقيق غير قابل للحذف يوثق كل إدخال (In)، إخراج (Out)، تسوية (Adjustment)، أو نقل (Transfer) مع اسم المستخدم والوقت والسبب.',
                steps: [
                    'فتح شاشة حركات المخزون والبحث برقم الحركة، الباركود، أو المرجع.',
                    'استعراض الأثر المخزني والتكلفة المرتبطة بكل سطر.'
                ],
                rules: [
                    'كل حركة تحمل مفتاح تفرد <code>movement_key</code> لمنع التكرار (Idempotency) في حال انقطاع الشبكة أو إعادة الإرسال.',
                    'لا يتم تعديل أي حركة مسجلة؛ أي تصحيح يتم عبر حركة تسوية جديدة متعاكسة.'
                ],
                permissions: ['inventory.movements.view']
            },
            {
                name: 'التسويات الجردية ومعالجة الفروقات (Stock Adjustments)',
                description: 'مطابقة الجرد الفعلي على أرض الواقع مع الرصيد الدفتري وتسجيل فروقات الزيادة أو العجز مع إبداء الأسباب.',
                steps: [
                    'إدخال الكمية المعدودة فعلياً في شاشة تسوية المخزون.',
                    'يقوم النظام بحساب الفارق (Variance) وإجراء حركة تسوية آلية.'
                ],
                rules: [
                    'الجرد الفعلي بيان حقيقة لا يُرفض، وتُسجل الحركة مع أثر مالي (مدين: خسائر جرد المخزون، دائن: المخزون في حال العجز).'
                ],
                permissions: ['inventory.manage']
            }
        ]
    },
    {
        id: 'wms-module',
        title: 'نظام إدارة المستودعات المتقدم (WMS - Warehouse Management)',
        shortTitle: 'إدارة المستودعات WMS',
        category: 'المخزون واللوجستيات',
        theme: 'teal',
        color: '#0d9488',
        icon: Location,
        primaryRoute: '/admin/wms',
        roles: ['admin', 'inventory'],
        description: 'نظام مستودعي عالي الكفاءة يدير المواقع التخزينية والحاويات (Bins)، قوائم الالتقاط والتعبئة، المناقلات بين الفروع، والجرد الدوري المستمر.',
        screens: [
            { name: 'نظرة عامة على WMS', path: '/admin/wms' },
            { name: 'دليل المستودعات والفروع', path: '/admin/wms/warehouses' },
            { name: 'مواقع التخزين والحاويات (Bins)', path: '/admin/wms/bins' },
            { name: 'قوائم الالتقاط (Picking Lists)', path: '/admin/wms/picking' },
            { name: 'قوائم التعبئة والتغليف (Packing Lists)', path: '/admin/wms/packing' },
            { name: 'الجرد الدوري (Cycle Counts)', path: '/admin/wms/cycle-counts' },
            { name: 'مناقلات المخزون بين الفروع (Transfers)', path: '/admin/wms/transfers' },
            { name: 'مؤشرات أداء المستودعات', path: '/admin/wms/performance' }
        ],
        deepNote: 'تتيح شجرة الحاويات (Bins) تقسيم المستودع إلى ممرات، رفوف، وخانات تخزين دقيقة لتسريع عملية تحضير وتجهيز الطلبيات بدقة 100%.',
        items: [
            {
                name: 'إدارة المستودعات ومواقع التخزين (Warehouses & Bins)',
                description: 'تعريف الفروع والمستودعات الرئيسية، وتقسيم كل مستودع إلى مناطق (Zones) وحاويات (Bins) مع تحديد السعة الاستيعابية ونوع التخزين.',
                steps: [
                    'إنشاء مستودع وتحديد أمين المستودع المسؤول وموقعه الجغرافي.',
                    'توليد رموز الحاويات (مثال: A-01-02 أي ممر A، رف 1، خانة 2) وطباعة باركود الحاويات.'
                ],
                rules: [
                    'يمكن تخصيص صنف معين لحاوية محددة أو السماح بالحاويات المختلطة.'
                ],
                permissions: ['wms.view', 'wms.warehouses.view', 'wms.bins.view']
            },
            {
                name: 'قوائم الالتقاط والتعبئة (Picking & Packing)',
                description: 'تنظيم مسار تحضير الطلبيات لأوامر البيع بحيث يسير عامل المستودع في أقصر مسار ممكن لجمع الأصناف والتأكد من مطابقتها قبل الشحن.',
                steps: [
                    'توليد قائمة التقاط من أمر البيع المعتمد.',
                    'مسح باركود الحاوية ثم باركود الصنف لتأكيد الجمع.',
                    'تحويل الأصناف المجمعة إلى محطة التعبئة والتغليف لإصدار ملصق الشحنة.'
                ],
                rules: [
                    'لا يسمح ببدء التعبئة حتى تكتمل قائمة الالتقاط بنسبة 100% أو تسجيل استثناء معتمد.'
                ],
                permissions: ['wms.picking.view', 'wms.packing.view']
            },
            {
                name: 'مناقلات المخزون بين الفروع (Warehouse Transfers Lifecycle)',
                description: 'نقل كميات من مستودع رئيسي إلى فرع فرعي بدورة تضمن عدم ضياع البضاعة: طلب نقل ← إرسال وخروج ← بضاعة بالطريق ← استلام واعتماد في الوجهة.',
                steps: [
                    'إنشاء أمر مناقلة وتحديد المستودع المصدر والمستودع الهدف والأصناف.',
                    'إخراج البضاعة من المصدر، لتتحول إلى حالة "بالطريق" (In-Transit).',
                    'عند وصول الشحنة للفرع، يقوم أمين مستودع الوجهة بفحصها وتأكيد الاستلام لتضاف إلى رصيده.'
                ],
                rules: [
                    'خلال مرحلة النقل، لا تكون البضاعة متاحة للبيع في أي من المستودعين حتى يؤكد الاستلام منعاً للازدواجية.'
                ],
                permissions: ['wms.transfers.view']
            },
            {
                name: 'الجرد الدوري المستمر (Cycle Counting)',
                description: 'إجراء جرد مستمر على عينات من الأصناف أو قطاعات محددة من الرفوف أسبوعياً دون الحاجة لإيقاف العمل وإغلاق المستودع بالكامل.',
                steps: [
                    'إنشاء خطة جرد دوري لقطاع محدد أو فئة أصناف عالية الحركة (ABC Analysis).',
                    'يقوم فريق الجرد بعد الأصناف على الأجهزة الكفية أو القوائم المطبوعة دون رؤية الرصيد الدفتري لضمان النزاهة (Blind Count).',
                    'مقارنة النتائج واعتماد الفروقات من قِبل المدير المسؤول.'
                ],
                rules: [
                    'يتم تعليق حركات الصرف على الحاويات المشمولة بالجرد أثناء فترة العد فقط.'
                ],
                permissions: ['wms.cycle_counts.view']
            }
        ]
    },
    {
        id: 'accounting-module',
        title: 'النظام المحاسبي والمالي المزدوج (Double-Entry Financial Accounting)',
        shortTitle: 'المحاسبة والمالية',
        category: 'المالية والمحاسبة',
        theme: 'violet',
        color: '#7c3aed',
        icon: Coin,
        primaryRoute: '/admin/accounting',
        roles: ['admin', 'accounting'],
        description: 'نواة الإدارة المالية: دليل الحسابات الشجري، قيود اليومية الآلية واليدوية، ميزان المراجعة، القوائم الختامية، أعمار الذمم، الأصول الثابتة، وضريبة القيمة المضافة.',
        screens: [
            { name: 'نظرة عامة على المالية', path: '/admin/accounting' },
            { name: 'دفتر اليومية العامة (Journal)', path: '/admin/accounting/journal' },
            { name: 'دليل الحسابات (Chart of Accounts)', path: '/admin/accounting/chart-of-accounts' },
            { name: 'دفتر الأستاذ العام (Ledger)', path: '/admin/accounting/ledger' },
            { name: 'ميزان المراجعة (Trial Balance)', path: '/admin/accounting/trial-balance' },
            { name: 'قائمة الدخل والأرباح (Income Statement)', path: '/admin/accounting/income-statement' },
            { name: 'الميزانية العمومية (Balance Sheet)', path: '/admin/accounting/balance-sheet' },
            { name: 'تقرير أعمار الذمم (Aging Report)', path: '/admin/accounting/aging' },
            { name: 'قائمة التدفقات النقدية (Cash Flow)', path: '/admin/accounting/cash-flow' },
            { name: 'كشف حساب الأطراف (Party Statement)', path: '/admin/accounting/party-statement' },
            { name: 'سجل الأصول الثابتة والإهلاك', path: '/admin/accounting/fixed-assets' },
            { name: 'التسوية البنكية ومطابقة الكشوف', path: '/admin/accounting/bank-reconciliation' },
            { name: 'مراكز التكلفة (Cost Centers)', path: '/admin/accounting/cost-centers' },
            { name: 'إقرار ضريبة القيمة المضافة (VAT Return)', path: '/admin/accounting/vat-return' },
            { name: 'إقفال الفترات المحاسبية', path: '/admin/accounting/periods' },
            { name: 'إدارة العملات وأسعار الصرف', path: '/admin/currencies' }
        ],
        deepNote: 'يدعم النظام قيود اليومية متعددة العملات مع حساب فروقات أسعار الصرف (Forex Gain/Loss) تلقائياً عند سداد الفواتير بعملات أجنبية.',
        items: [
            {
                name: 'دليل الحسابات الشجري (Chart of Accounts)',
                description: 'شجرة محاسبية هرمية متعددة المستويات مصنفة وفق المعايير المحاسبية المعتمدة: أصول، خصوم، حقوق ملكية، إيرادات، ومصروفات.',
                steps: [
                    'استعراض الحسابات الرئيسية والفرعية وإضافة حسابات جديدة حسب هيكل المنشأة.',
                    'تعيين نوع الحساب، طبيعته (مدين/دائن)، والعملة الافتراضية.'
                ],
                rules: [
                    'لا يسمح بإنشاء قيود يومية إلا على الحسابات من المستوى الأخير (حسابات فرعية ترحيلية).',
                    'لا يمكن حذف أي حساب يحمل حركات تاريخية مسجلة.'
                ],
                permissions: ['accounting.chart_of_accounts.view']
            },
            {
                name: 'دفتر اليومية العامة والقيود الآلية (Journal Entries)',
                description: 'تسجيل قيود التسوية والافتتاح اليدوية، بالإضافة للقيود المولدة آلياً من المبيعات، المشتريات، الدفعات، والرواتب.',
                steps: [
                    'إنشاء قيد يدوي، إدخال أسطر المدين والدائن، واختيار مركز التكلفة إن وجد.',
                    'التأكد من تساوي طرفي القيد (توازن صفري) ثم ترحيل القيد.'
                ],
                rules: [
                    'يمنع النظام حفظ أو ترحيل أي قيد غير متوازن <code>(مجموع المدين ≠ مجموع الدائن)</code>.',
                    'كل قيد يحمل ترقيماً تسلسلياً غير قابل للتعديل ومقترن بالسنة المالية.'
                ],
                permissions: ['accounting.journal.view']
            },
            {
                name: 'القوائم المالية والتقارير الختامية',
                description: 'توليد فوري لميزان المراجعة بالمجاميع والأرصدة، قائمة الدخل لتحديد صافي الأرباح التشغيلية، والميزانية العمومية الشاملة.',
                steps: [
                    'تحديد الفترة الزمنية، مركز التكلفة، أو العملة المطلوبة.',
                    'تصدير التقرير إلى Excel أو PDF للتدقيق المالي والمحاسبي.'
                ],
                rules: [
                    'تُحتسب تكلفة البضاعة المباعة تلقائياً بالاعتماد على مخرجات طبقات FIFO المخزنية.'
                ],
                permissions: ['accounting.trial_balance.view', 'accounting.income_statement.view', 'accounting.balance_sheet.view']
            },
            {
                name: 'كشف حساب الأطراف وأعمار الذمم (Aging & Statements)',
                description: 'كشوف حساب تفصيلية للعملاء والموردين توضح كل فاتورة ودفعة ورصيد تراكمي، مع تصنيف الذمم المستحقة (30، 60، 90، 120+ يوماً).',
                steps: [
                    'اختيار العميل أو المورد وتحديد الفترة الزمنية لطباعة كشف الحساب.',
                    'استعراض أعمار الذمم لتركيز جهود التحصيل والمتابعة القانونية.'
                ],
                rules: [
                    'يتاح لمندوب المبيعات استعراض كشوف حساب عملائه فقط لحماية سرية باقي العملاء.'
                ],
                permissions: ['accounting.party_statement.view', 'accounting.aging.view']
            },
            {
                name: 'إقفال الفترات المحاسبية (Accounting Periods Lock)',
                description: 'قفل الأشهر أو السنوات المالية المنتهية لحماية البيانات المحاسبية المدققة من أي إضافة أو تعديل بأثر رجعي.',
                steps: [
                    'مراجعة قيود الشهر وتسوية كافة الحسابات.',
                    'الضغط على إقفال الفترة لمنع أي مستخدم من تسجيل حركات بتاريخ يقع ضمن الفترة المقفلة.'
                ],
                rules: [
                    'لا يمكن إعادة فتح فترة مقفلة إلا بصلاحية مدير النظام العليا (Master/Super Admin).'
                ],
                permissions: ['accounting.periods.view']
            }
        ]
    },
    {
        id: 'hr-module',
        title: 'الموارد البشرية، الحضور، ومسيرات الرواتب (Human Resources - HR)',
        shortTitle: 'الموارد البشرية والرواتب',
        category: 'الموارد البشرية',
        theme: 'pink',
        color: '#db2777',
        icon: UserFilled,
        primaryRoute: '/admin/hr',
        roles: ['admin', 'hr', 'accounting'],
        description: 'إدارة شؤون الموظفين، ربط المناديب بالعملاء والفروع، سجلات الحضور والانصراف، الإجازات، مسيرات الرواتب الشهرية، وكشوف عمولات المبيعات.',
        screens: [
            { name: 'نظرة عامة على الموارد البشرية', path: '/admin/hr' },
            { name: 'سجل الموظفين والملفات الشخصية', path: '/admin/hr/employees' },
            { name: 'علاقة الموظفين والمناديب بالعملاء', path: '/admin/hr/employee-customers' },
            { name: 'سجلات الحضور والانصراف اليومي', path: '/admin/hr/attendance' },
            { name: 'طلبات وأرصدة الإجازات', path: '/admin/hr/leaves' },
            { name: 'مسيرات الرواتب الشهرية (Payrolls)', path: '/admin/hr/payrolls' },
            { name: 'كشوف عمولات المناديب (Commissions)', path: '/admin/hr/commissions' }
        ],
        deepNote: 'يرتبط سجل الموظف اختيارياً بحساب مستخدم (User) ومستودع محدد (Warehouse)، وهو ما يحدد نطاق صلاحيات تطبيق المندوب الميداني.',
        items: [
            {
                name: 'سجل الموظفين وتعيين الفروع والعملاء',
                description: 'إدارة ملفات الموظفين: البيانات الشخصية، الراتب الأساسي، البدلات، المسمى الوظيفي، وتعيين المحفظة الجغرافية أو العملاء للمناديب.',
                steps: [
                    'إضافة موظف جديد وتحديد فرعه ومسماه الوظيفي ومستحقاته المالية.',
                    'في شاشة "علاقة الموظفين بالعملاء": توزيع العملاء على المناديب لضبط مستهدفات البيع.'
                ],
                rules: [
                    'المندوب الميداني لا يستطيع استعراض أو الفوترة إلا للعملاء المربوطين بحسابه فقط.'
                ],
                permissions: ['hr.view', 'hr.employees.view', 'hr.employee_customers.view']
            },
            {
                name: 'الحضور، الانصراف، والإجازات (Attendance & Leaves)',
                description: 'تسجيل مواعيد الحضور والانصراف، حساب ساعات العمل الإضافي والتأخير، وإدارة رصيد الإجازات السنوية والموافقة الإدارية عليها.',
                steps: [
                    'استيراد أو تسجيل سجلات الحضور اليومي للموظفين.',
                    'تقديم طلب إجازة ومراجعته من قِبل المشرف للخصم التلقائي من رصيد الإجازات.'
                ],
                rules: [
                    'الغياب غير المبرر ينعكس تلقائياً كاستقطاع في مسير الرواتب الشهري.'
                ],
                permissions: ['hr.attendance.view', 'hr.leaves.view']
            },
            {
                name: 'مسيرات الرواتب واحتساب العمولات (Payrolls & Commissions)',
                description: 'توليد مسير الرواتب الشهري التلقائي باحتساب: الأساسي + البدلات + الإضافي + عمولات البيع − الاستقطاعات والخصومات.',
                steps: [
                    'توليد مسير الشهر ومراجعته بدقة لكل موظف.',
                    'اعتماد المسير، ليقوم النظام بإنشاء سندات الصرف وترحيل القيد المحاسبي التلقائي للرواتب.'
                ],
                rules: [
                    'معادلة صافي الراتب: <code>صافي الراتب = الأساسي + البدلات + المكافآت والعمولات - الاستقطاعات</code>.',
                    'ترقيم مسيرات الرواتب تلقائي متسلسل بنمط <code>PAY-######</code>.'
                ],
                permissions: ['hr.payrolls.view', 'hr.commissions.view']
            }
        ]
    },
    {
        id: 'crm-module',
        title: 'إدارة علاقات العملاء وتذاكر الدعم (CRM & Tickets)',
        shortTitle: 'علاقات العملاء CRM',
        category: 'الخدمات والدعم',
        theme: 'sky',
        color: '#0284c7',
        icon: ChatDotRound,
        primaryRoute: '/admin/crm',
        roles: ['admin', 'sales'],
        description: 'بناء سجل ولاء شامل لكل عميل، متابعة التفاعلات والاتصالات، إدارة تذاكر الشكاوى والدعم الفني ومتابعة سرعة حلها.',
        screens: [
            { name: 'نظرة عامة على CRM', path: '/admin/crm' },
            { name: 'قاعدة بيانات العملاء والتفضيلات', path: '/admin/crm/customers' },
            { name: 'تذاكر الدعم الفني والشكاوى', path: '/admin/crm/tickets' }
        ],
        deepNote: 'تتكامل وحدة CRM مباشرة مع المبيعات والفواتير لرؤية سجل مشتريات العميل أثناء الرد على اتصاله أو معالجة شكواه.',
        items: [
            {
                name: 'سجل العميل الشامل (Customer 360 View)',
                description: 'نافذة شاملة تعرض بيانات التواصل، حد الائتمان، المندوب المسؤول، سجل الفواتير، الدفعات، وأي تذاكر دعم سابقة.',
                steps: [
                    'فتح ملف العميل من قائمة CRM أو المبيعات.',
                    'تسجيل ملاحظات الاتصال، ومتابعة درجة رضى العميل وتصنيفه التجاري (VIP / قياسي).'
                ],
                rules: [
                    'يمكن ربط جهات اتصال فرعية متعددة (Contacts) لكل شركة أو عميل تجاري.'
                ],
                permissions: ['crm.view', 'crm.customers.view']
            },
            {
                name: 'تذاكر الدعم الفني ومتابعة الشكاوى (Support Tickets)',
                description: 'فتح تذاكر للملاحظات أو عيوب التركيب أو تأخر التسليم، وتعيينها للموظف المسؤول مع تتبع أوقات الاستجابة والإغلاق.',
                steps: [
                    'فتح تذكرة وتحديد الأولوية (عاجل، عادي، منخفض).',
                    'إرسال إشعار للموظف المعني وتحديث حالة التذكرة حتى حلها.'
                ],
                rules: [
                    'تظهر التذاكر المفتوحة كإنذار في لوحة التحكم حتى يتم إغلاقها برضى العميل.'
                ],
                permissions: ['crm.tickets.view']
            }
        ]
    },
    {
        id: 'reports-analytics-module',
        title: 'التقارير المتقدمة وذكاء الأعمال (Reports & BI Analytics)',
        shortTitle: 'التقارير وذكاء الأعمال',
        category: 'التقارير والتحليلات',
        theme: 'cyan',
        color: '#0891b2',
        icon: DataAnalysis,
        primaryRoute: '/admin/reports',
        roles: ['admin', 'accounting', 'sales'],
        description: 'مركز اتخاذ القرار: تقارير تشغيلية وتفصيلية، ولوحات ذكاء أعمال (BI) تفاعلية ترصد اتجاهات المبيعات، ربحية الأصناف، ودوران المخزون.',
        screens: [
            { name: 'نظرة عامة على التقارير', path: '/admin/reports' },
            { name: 'تقارير المبيعات التفصيلية', path: '/admin/reports/sales' },
            { name: 'تقارير حركة وركود المخزون', path: '/admin/reports/inventory' },
            { name: 'التقارير المالية والضريبية', path: '/admin/reports/financial' },
            { name: 'تقارير تكلفة الرواتب', path: '/admin/reports/payroll' },
            { name: 'لوحات تحليلات ذكاء الأعمال BI', path: '/admin/analytics' }
        ],
        deepNote: 'تدعم التقارير التصدير المباشر بصيغة Excel وPDF والطباعة، مع خيارات فلترة متقدمة حسب التواريخ، الفروع، والمندوبين.',
        items: [
            {
                name: 'تقارير حركة وربحية المبيعات',
                description: 'تحليل دقيق للمبيعات حسب الصنف، الفئة، العميل، أو المندوب مع حساب هامش الربح الإجمالي وصافي المبيعات بعد الخصم.',
                steps: [
                    'تحديد الفترة وتطبيق الفلاتر المطلوبة.',
                    'استعراض الرسوم البيانية والجداول، وتصدير الملف بنقرة واحدة.'
                ],
                rules: [
                    'يتم احتساب الربح بناءً على تكلفة FIFO التاريخية للدفعة المباعة لضمان دقة هامش الربح.'
                ],
                permissions: ['reports.sales.view']
            },
            {
                name: 'تحليلات ركود المخزون ونقاط الطلب',
                description: 'كشف الأصناف الراكدة بطيئة الحركة (Slow-Moving) لعمل عروض عليها، وتنبيهات الأصناف المقتربة من نقطة إعادة الطلب.',
                steps: [
                    'فتح تقرير المخزون واختيار نمط "الأصناف تحت نقطة الطلب" لتوليد أوامر شراء سريعة.'
                ],
                rules: [
                    'تعتمد نقطة الطلب الديناميكية على: <code>متوسط البيع لآخر 30 يوماً × مهلة التوريد + مخزون الأمان</code>.'
                ],
                permissions: ['reports.inventory.view']
            }
        ]
    },
    {
        id: 'workflows-module',
        title: 'محرك أتمتة مسارات العمل والموافقات (Workflows)',
        shortTitle: 'مسارات العمل والموافقات',
        category: 'الأنظمة الذكية',
        theme: 'purple',
        color: '#9333ea',
        icon: Cpu,
        primaryRoute: '/admin/workflows',
        roles: ['admin'],
        description: 'أتمتة العمليات الإدارية: تحديد شروط الاعتماد لسلسلة من الموافقات (مثل: موافقة المدير المالي إذا تجاوز الخصم 10% أو تجاوزت الفاتورة مبلغاً معيناً).',
        screens: [
            { name: 'قائمة مسارات العمل', path: '/admin/workflows' },
            { name: 'بناء مسار عمل جديد', path: '/admin/workflows/create' }
        ],
        deepNote: 'يمنع محرك مسارات العمل تجاوز السياسات الائتمانية والمالية الموضوعة من الإدارة العليا دون الحصول على التوقيع الرقمي للمسؤول.',
        items: [
            {
                name: 'بناء وتخصيص قواعد سير العمل (Workflow Rules)',
                description: 'تحديد الحدث المشغل (Trigger) والشروط المسبقة، وتسلسل الموافقات المطلوبة وصولاً للاعتماد النهائي.',
                steps: [
                    'إنشاء مسار جديد (مثال: طلب إجازة، طلب شراء بمبلغ كبير، أو خصم بيع استثنائي).',
                    'تحديد سلسلة الرتب المعتمدة (المشرف المباشر ← مدير الفرع ← الإدارة العامة).'
                ],
                rules: [
                    'تظل المعاملة في حالة "معلقة" (Pending Approval) ولا يمكن ترحيلها حتى تكتمل كافة الموافقات.'
                ],
                permissions: ['workflows.view', 'workflows.create']
            }
        ]
    },
    {
        id: 'system-security-module',
        title: 'الأمان، الصلاحيات، وإدارة المستخدمين (RBAC & System Security)',
        shortTitle: 'الأمان والصلاحيات',
        category: 'إدارة النظام والأمان',
        theme: 'slate',
        color: '#334155',
        icon: Setting,
        primaryRoute: '/admin/users',
        roles: ['admin'],
        description: 'التحكم المركزي في حسابات الدخول، مصفوفة الصلاحيات الدقيقة لكل شاشة وإجراء، سجلات التدقيق والرقابة (Audit Trail)، وإعدادات النظام العامة.',
        screens: [
            { name: 'إدارة المستخدمين', path: '/admin/users' },
            { name: 'الأدوار والمجموعات (Roles)', path: '/admin/roles' },
            { name: 'مصفوفة الصلاحيات (Permissions)', path: '/admin/permissions' },
            { name: 'سجلات التدقيق والمراقبة (Audit Logs)', path: '/admin/audit' },
            { name: 'مركز الإشعارات والتفضيلات', path: '/admin/notifications' },
            { name: 'إدارة الميزات والوحدات (Features)', path: '/admin/features' },
            { name: 'الإعدادات العامة للنظام', path: '/admin/settings' }
        ],
        deepNote: 'يحمل النظام نموذج صلاحيات متقدم يتيح منح الصلاحية على مستوى الشاشة ككل، أو على مستوى الإجراء الدقيق (عرض، إضافة، تعديل، حذف، طباعة، ترحيل).',
        items: [
            {
                name: 'مصفوفة الصلاحيات والأدوار (Role-Based Access Control)',
                description: 'تخصيص الصلاحيات لكل دور وظيفي (أمين مستودع، كاشير، مندوب مبيعات، محاسب، مدير عام) وعزل البيانات الحساسة.',
                steps: [
                    'الدخول إلى "الأدوار" واختيار الدور المراد تعديل صلاحياته.',
                    'تفعيل أو تعطيل الشاشات والأزرار المسموح للمستخدمين المنتمين لهذا الدور بالوصول إليها.'
                ],
                rules: [
                    'تطبق الصلاحيات في الواجهة الأمامية والشريط الجانبي، ويتم التحقق منها إجبارياً على مستوى خادم الـ API عبر Middleware مخصص.'
                ],
                permissions: ['roles.view', 'permissions.view', 'users.view']
            },
            {
                name: 'سجلات التدقيق والمراقبة الجنائية (Audit Logs & History)',
                description: 'توثيق غير قابل للمسح لكل عملية دخول، خروج، إنشاء، تعديل، أو حذف، مع تسجيل القيمة القديمة والجديدة وعنوان IP.',
                steps: [
                    'فتح شاشة سجلات التدقيق وتصفية السجلات باسم الموظف أو الوحدة أو التاريخ.',
                    'مقارنة التغييرات التي طرأت على أي سجل (Before / After Snapshot).'
                ],
                rules: [
                    'سجلات التدقيق محمية برمجياً ولا يمكن حذفها حتى من قِبل مدراء النظام.'
                ],
                permissions: ['audit.view']
            },
            {
                name: 'إدارة تفعيل وتعطيل وحدات النظام (Features Toggle)',
                description: 'لوحة تحكم للمدير الأعلى (Master Admin) تتيح تفعيل أو إيقاف تشغيل أي وحدة برمجية في النظام للمنشأة وفق باقتها واحتياجها.',
                steps: [
                    'الدخول لشاشة "إدارة الميزات" وتفعيل/تعطيل الوحدات (المحاسبة، WMS، الموارد البشرية، الإنتاج).'
                ],
                rules: [
                    'تعطيل أي وحدة يخفيها فورياً من الشريط الجانبي ويقفل واجهاتها البرمجية دون مساس بالبيانات التاريخية.'
                ],
                permissions: ['masterOnly']
            }
        ]
    }
];

// Technical Glossary terms
const glossaryTerms = [
    {
        term: 'الوارد أولاً يصرف أولاً (FIFO)',
        en: 'First-In, First-Out',
        definition: 'مبدأ محاسبي ومخزني يفترض أن الأصناف التي يتم شراؤها واستلامها أولاً هي التي تباع وتخرج أولاً، مما يجعل تقييم المخزون المتبقي في المستودع بأحدث الأسعار السوقية.',
        formula: 'COGS = ∑ (كمية البند × تكلفة أقدم طبقة واردة متبقية)'
    },
    {
        term: 'المخزون القابل للبيع (Sellable Stock)',
        en: 'Available Sellable Stock',
        definition: 'الكمية الفعلية الجاهزة للإدراج في فواتير ومبيعات جديدة دون التعدي على طلبات سابقة محجوزة لعملاء آخرين أو أصناف معيبة.',
        formula: 'Sellable = max(0, available_quantity - reserved_quantity)'
    },
    {
        term: 'نقطة إعادة الطلب الديناميكية',
        en: 'Dynamic Reorder Point (ROP)',
        definition: 'الحد الأدنى الذي إذا انخفض رصيد الصنف إليه يقوم النظام بتنبيه مسؤول المشتريات لإصدار أمر توريد جديد قبل نفاد المخزون.',
        formula: 'ROP = (متوسط المبيعات اليومية × مهلة التوريد بالأيام) + مخزون الأمان'
    },
    {
        term: 'الحاويات والمواقع التخزينية (Bins)',
        en: 'Warehouse Bins & Locations',
        definition: 'أصغر وحدة تخزين جغرافية داخل المستودع (ممر - رف - خانة) يربط بها الصنف لتسهيل عمليات الالتقاط والجرد الآلي.',
        formula: 'رمز الحاوية: [المنطقة]-[الممر]-[الرف]-[الخانة]'
    },
    {
        term: 'إشعار دائن (Credit Note)',
        en: 'Credit Note / Memo',
        definition: 'وثيقة قانونية ومحاسبية تصدر للعميل تثبت استحقاقه لمبلغ مالي ناتج عن إرجاع بضاعة أو خصم تجاري وتستخدم لسداد فواتير لاحقة أو استرداد نقدي.',
        formula: 'قيد: مدين: مردودات المبيعات والضريبة | دائن: العميل'
    },
    {
        term: 'مفتاح عدم التكرار (Idempotency Key)',
        en: 'Movement Key / Idempotency',
        definition: 'رمز فريد يولد مع كل حركة مخزنية أو قيد لمنع تنفيذ نفس العملية مرتين في حال ضغط المستخدم المتكرر أو انقطاع الاتصال.',
        formula: 'Unique Hash Key = sha256(ref + type + timestamp + payload)'
    },
    {
        term: 'فروقات أسعار الصرف (Forex)',
        en: 'Forex Realized Gain / Loss',
        definition: 'الفارق المالي الذي يظهر بين تاريخ إصدار فاتورة المبيعات/المشتريات بعملة أجنبية وتاريخ تحصيلها الفعلي بسبب تذبذب سعر الصرف.',
        formula: 'Forex = المبلغ بالعملة الأجنبية × (سعر الصرف يوم الدفع - سعر الصرف يوم الفاتورة)'
    }
];

// Frequently Asked Questions
const faqList = [
    {
        q: 'لماذا تظهر رسالة "الكمية المطلوبة تتجاوز المخزون المتاح" بالرغم من وجود بضاعة على الرف؟',
        a: 'يرجع ذلك لوجود جزء من الكمية محجوزاً في "أوامر بيع" سابقة لم يتم شحنها بعد، أو أن جزءاً منها مصنف كبضاعة تالفة أو محجورة للفحص. يفحص النظام الكمية المتاحة للبيع وفق المعادلة: (السليم - المحجوز) لحماية التزاماتك مع باقي العملاء.'
    },
    {
        q: 'كيف يمكن تعديل فاتورة مبيعات تم ترحيلها واعتمادها؟',
        a: 'حفاظاً على المعايير المحاسبية والقانونية وسلامة قيود اليومية، لا يسمح النظام بتعديل الفواتير المعتمدة مباشرة. لمعالجة أي خطأ يتم إصدار طلب إرجاع (RMA) أو إشعار دائن للمرتجعات، ثم إصدار فاتورة جديدة صحيحة.'
    },
    {
        q: 'هل يؤثر أمر الشراء (PO) على رصيد المخزون في المستودعات؟',
        a: 'لا، أمر الشراء هو وثيقة تعاقدية مع المورد ولا يزيد الرصيد الفعلي. يزيد رصيد المخزون فقط عند إصدار "إذن استلام مخزني" (Goods Receipt) وتأكيد وصول البضاعة فعلياً إلى المستودع.'
    },
    {
        q: 'كيف أستطيع منع موظف أو كاشير من رؤية التكلفة الحقيقية وأرباح المبيعات؟',
        a: 'من خلال شاشة "الأدوار والصلاحيات"، يتم سحب صلاحية "accounting.view" وصلاحيات "reports.sales.view" من دوره الوظيفي، وتحديد صلاحياته على "sales.create" و"pos.view" فقط.'
    },
    {
        q: 'ماذا يحدث إذا انقطع الاتصال بالإنترنت أثناء قيام الكاشير بحفظ الفاتورة؟',
        a: 'النظام مزود بمفاتيح أمان ذكية (Idempotency Keys). بمجرد عودة الاتصال أو إعادة الإرسال يقوم النظام بالتحقق؛ إذا كانت الفاتورة قد وصلت للخادم يتم عرضها فوراً دون تكرار خصم المخزون أو تكرار القيد المحاسبي.'
    }
];

// Computed counts
const modulesCount = computed(() => sectionsData.length);
const screensCount = computed(() => sectionsData.reduce((acc, s) => acc + (s.screens?.length || 0), 0));
const rulesCount = computed(() => sectionsData.reduce((acc, s) => acc + s.items.reduce((iAcc, it) => iAcc + (it.rules?.length || 0), 0), 0));

// Filtered sections based on search query and role filter
const filteredSections = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const role = selectedRole.value;

    return sectionsData.filter(section => {
        // Role check
        if (role !== 'all' && section.roles && !section.roles.includes(role)) {
            return false;
        }

        // Search check
        if (!q) return true;

        const inTitle = section.title.toLowerCase().includes(q) || section.shortTitle?.toLowerCase().includes(q);
        const inDesc = section.description.toLowerCase().includes(q);
        const inScreens = section.screens?.some(sc => sc.name.toLowerCase().includes(q) || sc.path.toLowerCase().includes(q));
        const inItems = section.items?.some(it =>
            it.name.toLowerCase().includes(q) ||
            it.description.toLowerCase().includes(q) ||
            it.steps?.some(st => st.toLowerCase().includes(q)) ||
            it.rules?.some(rl => rl.toLowerCase().includes(q))
        );

        return inTitle || inDesc || inScreens || inItems;
    });
});

const totalCount = computed(() => sectionsData.length);
const filteredCount = computed(() => filteredSections.value.length);

const allExpanded = computed(() => {
    return filteredSections.value.every(s => expandedSections.value.has(s.id));
});

// Section expand/collapse controls
const isSectionExpanded = (id) => expandedSections.value.has(id);

const toggleSection = (id) => {
    if (expandedSections.value.has(id)) {
        expandedSections.value.delete(id);
    } else {
        expandedSections.value.add(id);
    }
};

const toggleAllSections = () => {
    if (allExpanded.value) {
        expandedSections.value.clear();
    } else {
        filteredSections.value.forEach(s => expandedSections.value.add(s.id));
        expandedSections.value.add('system-architecture');
        expandedSections.value.add('system-glossary');
        expandedSections.value.add('system-faq');
    }
};

const clearSearch = () => {
    searchQuery.value = '';
    selectedRole.value = 'all';
};

// Copy link anchor to clipboard
const copyAnchor = (id) => {
    const url = `${window.location.origin}${window.location.pathname}#${id}`;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
            ElMessage.success('تم نسخ رابط القسم إلى الحافظة بنجاح');
        }).catch(() => {
            fallbackCopy(url);
        });
    } else {
        fallbackCopy(url);
    }
};

const fallbackCopy = (text) => {
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    ElMessage.success('تم نسخ رابط القسم إلى الحافظة');
};

// Scroll spy & Smooth navigation
const scrollToSection = (id) => {
    activeSectionId.value = id;
    expandedSections.value.add(id);
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const printGuide = () => {
    activeViewTab.value = 'modules';
    filteredSections.value.forEach(s => expandedSections.value.add(s.id));
    expandedSections.value.add('system-architecture');
    expandedSections.value.add('system-glossary');
    expandedSections.value.add('system-faq');
    setTimeout(() => {
        window.print();
    }, 250);
};

// Keyboard listener for '/' search shortcut
const handleKeyDown = (e) => {
    if (e.key === '/' && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
        e.preventDefault();
        searchInputRef.value?.focus();
    }
};

const handleScroll = () => {
    showBackToTop.value = window.scrollY > 400;
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('keydown', handleKeyDown);

    // If an anchor hash exists in URL, scroll to it
    if (window.location.hash) {
        const hashId = window.location.hash.substring(1);
        nextTick(() => {
            scrollToSection(hashId);
        });
    }
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<style scoped>
.system-documentation {
    font-family: inherit;
    color: #1e293b;
    padding-bottom: 5rem;
}

/* Hero Section */
.doc-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #1e3a8a 100%);
    color: #f8fafc;
    border-radius: 18px;
    padding: 2.75rem 2.25rem 2.25rem;
    margin-bottom: 2rem;
    box-shadow: 0 15px 35px -10px rgba(15, 23, 42, 0.35);
    position: relative;
    overflow: hidden;
}

.doc-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -20%;
    width: 60%;
    height: 200%;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.18) 0%, transparent 70%);
    pointer-events: none;
}

.doc-hero-badges {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.system-badge {
    background: rgba(59, 130, 246, 0.22);
    color: #93c5fd;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.3rem 0.85rem;
    border-radius: 9999px;
    border: 1px solid rgba(147, 197, 253, 0.35);
}

.version-badge {
    background: rgba(16, 185, 129, 0.22);
    color: #6ee7b7;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.3rem 0.85rem;
    border-radius: 9999px;
    border: 1px solid rgba(110, 231, 183, 0.35);
}

.reading-time-badge {
    background: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.3rem 0.85rem;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.doc-hero-title {
    font-size: 2.25rem;
    font-weight: 900;
    margin: 0 0 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    color: #ffffff;
    letter-spacing: -0.5px;
}

.hero-icon {
    color: #60a5fa;
}

.doc-hero-desc {
    font-size: 1.05rem;
    line-height: 1.75;
    color: #cbd5e1;
    max-width: 950px;
    margin-bottom: 1.75rem;
}

.doc-toolbar {
    display: flex;
    gap: 1rem;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(8px);
    padding: 1.15rem;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.search-box {
    flex: 1;
    min-width: 290px;
}

.search-shortcut {
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    color: #cbd5e1;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 4px;
    padding: 2px 7px;
    line-height: 1;
}

.results-badge {
    font-size: 0.8rem;
    color: #60a5fa;
    background: rgba(59, 130, 246, 0.15);
    padding: 2px 8px;
    border-radius: 6px;
    font-weight: 600;
}

/* Quick Search Tags */
.quick-tags-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1.15rem;
    flex-wrap: wrap;
}

.quick-tags-lbl {
    font-size: 0.82rem;
    color: #94a3b8;
    font-weight: 600;
}

.quick-tags-list {
    display: flex;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.quick-tag-chip {
    background: rgba(255, 255, 255, 0.06);
    color: #cbd5e1;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 6px;
    padding: 0.25rem 0.65rem;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.18s ease;
}

.quick-tag-chip:hover, .quick-tag-chip.active {
    background: rgba(59, 130, 246, 0.25);
    color: #93c5fd;
    border-color: #60a5fa;
}

/* Role Filter Pills */
.role-pills-bar {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 1.25rem;
    flex-wrap: wrap;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.role-filter-label {
    font-size: 0.85rem;
    color: #94a3b8;
    font-weight: 600;
}

.role-pills {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.role-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.95rem;
    border-radius: 9px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #e2e8f0;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    cursor: pointer;
    transition: all 0.2s ease;
}

.role-pill:hover {
    background: rgba(255, 255, 255, 0.16);
    border-color: rgba(255, 255, 255, 0.28);
}

.role-pill.active {
    background: #2563eb;
    color: #ffffff;
    border-color: #60a5fa;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

/* View Mode Tabs */
.view-tabs-container {
    background: #ffffff;
    border-radius: 14px;
    padding: 0.5rem 1.5rem 0;
    border: 1px solid #e2e8f0;
    margin-bottom: 1.75rem;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.04);
}

.view-tab-label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 0.95rem;
    padding: 0.5rem 0;
}

.tab-badge {
    font-size: 0.75rem;
    background: #f1f5f9;
    color: #475569;
    padding: 1px 7px;
    border-radius: 9999px;
    font-weight: 700;
}

.tab-badge.special {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}

/* Body Layout: Sidebar + Main Content */
.doc-body-wrapper {
    display: grid;
    grid-template-columns: 290px 1fr;
    gap: 1.75rem;
    align-items: start;
}

/* Sidebar Nav */
.doc-sidebar-nav {
    position: sticky;
    top: 90px;
}

.nav-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 1.25rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05);
}

.nav-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 0.75rem;
}

.nav-links-list {
    list-style: none;
    padding: 0;
    margin: 0 0 1rem;
    max-height: calc(100vh - 310px);
    overflow-y: auto;
}

.nav-item-link {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.55rem 0.7rem;
    border-radius: 8px;
    text-decoration: none;
    color: #475569;
    font-size: 0.86rem;
    font-weight: 600;
    transition: all 0.18s ease;
    margin-bottom: 0.25rem;
}

.nav-item-link:hover {
    background: #f8fafc;
    color: #0f172a;
}

.nav-item-link.active {
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 800;
}

.nav-icon-badge {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.nav-text {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nav-badge {
    font-size: 0.72rem;
    background: #f1f5f9;
    color: #64748b;
    padding: 1px 6px;
    border-radius: 9999px;
    font-weight: 700;
}

.quick-stats-box {
    display: flex;
    align-items: center;
    justify-content: space-around;
    padding-top: 0.85rem;
    border-top: 1px solid #f1f5f9;
    text-align: center;
}

.stat-item {
    display: flex;
    flex-direction: column;
}

.stat-num {
    font-size: 1.2rem;
    font-weight: 900;
    color: #0f172a;
}

.stat-lbl {
    font-size: 0.72rem;
    color: #64748b;
}

.stat-sep {
    width: 1px;
    height: 26px;
    background: #e2e8f0;
}

/* Master Blueprint Card */
.master-blueprint-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 4px 18px -4px rgba(0, 0, 0, 0.06);
    padding: 2rem;
    margin-bottom: 1.75rem;
}

.blueprint-header {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.blueprint-title-wrap {
    flex: 1;
}

.section-anchor-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.blueprint-tag {
    font-size: 0.78rem;
    font-weight: 800;
    color: #2563eb;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.copy-anchor-btn {
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 0.95rem;
    padding: 3px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.copy-anchor-btn:hover {
    color: #2563eb;
    background: #eff6ff;
}

.copy-anchor-btn.small {
    font-size: 0.8rem;
}

.blueprint-desc {
    font-size: 0.98rem;
    line-height: 1.75;
    color: #334155;
    margin-bottom: 1.75rem;
}

.blueprint-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.35rem;
    margin-bottom: 1.75rem;
}

.blueprint-box {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1.35rem;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}

.blueprint-box:hover {
    border-color: #93c5fd;
    background: #ffffff;
    box-shadow: 0 6px 16px -2px rgba(59, 130, 246, 0.08);
}

.bp-icon-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
    color: #1e3a8a;
}

.bp-icon-title h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
}

.blueprint-box ul {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 0.88rem;
    line-height: 1.65;
    color: #475569;
}

.blueprint-box ul li {
    position: relative;
    padding-right: 1.2rem;
    margin-bottom: 0.6rem;
}

.blueprint-box ul li::before {
    content: '•';
    position: absolute;
    right: 0;
    color: #3b82f6;
    font-weight: bold;
    font-size: 1.2rem;
}

.lifecycle-flow {
    background: #f1f5f9;
    border-radius: 14px;
    padding: 1.5rem;
    border: 1px solid #e2e8f0;
}

.flow-title {
    margin: 0 0 1.25rem;
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    text-align: center;
}

.flow-steps {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.flow-step {
    flex: 1;
    min-width: 140px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 0.85rem 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    box-shadow: 0 2px 5px rgba(0,0,0,0.03);
}

.step-num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #2563eb;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.step-content {
    display: flex;
    flex-direction: column;
}

.step-content strong {
    font-size: 0.85rem;
    color: #0f172a;
}

.step-content span {
    font-size: 0.72rem;
    color: #64748b;
}

.flow-arrow {
    font-size: 1.35rem;
    color: #94a3b8;
    font-weight: bold;
}

/* Documentation Section Cards */
.doc-section-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 14px -3px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.75rem;
    overflow: hidden;
    transition: all 0.22s ease;
}

.doc-section-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 8px 20px -3px rgba(0, 0, 0, 0.08);
}

.section-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 1.75rem;
    cursor: pointer;
    background: #ffffff;
    user-select: none;
    border-bottom: 1px solid #f1f5f9;
}

.header-main {
    display: flex;
    align-items: flex-start;
    gap: 1.25rem;
}

.section-icon-wrap {
    width: 50px;
    height: 50px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.header-titles {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.header-tag-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.sub-counter {
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 600;
}

.section-heading {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.3px;
}

.section-summary {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.55;
    color: #475569;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.quick-jump-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 0.95rem;
    border-radius: 9px;
    background: #f1f5f9;
    color: #1e293b;
    text-decoration: none;
    font-size: 0.84rem;
    font-weight: 700;
    transition: all 0.2s ease;
    border: 1px solid #cbd5e1;
}

.quick-jump-btn:hover {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.expand-icon-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    font-size: 1.15rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.expand-icon-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.expand-icon-btn .el-icon {
    transition: transform 0.25s ease;
}

.expand-icon-btn .el-icon.rotated {
    transform: rotate(180deg);
}

.section-card-body {
    padding: 1.75rem;
    background: #fafbfc;
}

/* Screens Chips Bar */
.screens-bar {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1.15rem;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.screens-title {
    font-size: 0.84rem;
    font-weight: 800;
    color: #334155;
}

.screen-chips {
    display: flex;
    gap: 0.55rem;
    flex-wrap: wrap;
}

.screen-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    padding: 0.3rem 0.7rem;
    font-size: 0.8rem;
    color: #1e293b;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.18s ease;
}

.screen-chip:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
}

.chip-path {
    font-size: 0.72rem;
    color: #64748b;
    background: #f1f5f9;
    padding: 1px 5px;
    border-radius: 4px;
}

/* Features Grid */
.features-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.35rem;
}

.feature-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.35rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px -1px rgba(0, 0, 0, 0.04);
}

.feature-head {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-bottom: 0.6rem;
}

.feature-num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}

.feature-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
}

.feature-desc {
    margin: 0 0 1.15rem;
    font-size: 0.9rem;
    line-height: 1.65;
    color: #475569;
}

.feature-steps-box {
    background: #f8fafc;
    border-radius: 10px;
    padding: 0.95rem 1.15rem;
    border: 1px solid #e2e8f0;
    margin-bottom: 0.95rem;
}

.steps-heading {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.85rem;
    font-weight: 800;
    color: #0f766e;
    margin-bottom: 0.65rem;
}

.steps-list {
    margin: 0;
    padding-right: 1.35rem;
    font-size: 0.86rem;
    line-height: 1.7;
    color: #334155;
}

.steps-list li {
    margin-bottom: 0.4rem;
}

.feature-rules-box {
    background: #fffbeb;
    border-radius: 10px;
    padding: 0.95rem 1.15rem;
    border: 1px solid #fef3c7;
    margin-bottom: 0.95rem;
}

.rules-heading {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.85rem;
    font-weight: 800;
    color: #b45309;
    margin-bottom: 0.65rem;
}

.rules-list {
    margin: 0;
    padding-right: 1.35rem;
    list-style: square;
    font-size: 0.86rem;
    line-height: 1.7;
    color: #78350f;
}

.rules-list li {
    margin-bottom: 0.4rem;
}

.feature-perm-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.8rem;
    padding-top: 0.65rem;
    border-top: 1px dashed #e2e8f0;
}

.perm-label {
    color: #64748b;
    font-weight: 700;
}

.perm-tags {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.perm-tag {
    background: #f1f5f9;
    color: #1e293b;
    padding: 2px 7px;
    border-radius: 5px;
    font-family: monospace;
    font-size: 0.78rem;
    font-weight: 600;
}

.section-deep-note {
    display: flex;
    align-items: flex-start;
    gap: 0.95rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
    padding: 1.15rem 1.35rem;
    margin-top: 1.5rem;
}

.note-icon {
    color: #2563eb;
    font-size: 1.35rem;
    margin-top: 2px;
}

.note-content strong {
    display: block;
    font-size: 0.92rem;
    color: #1e40af;
    margin-bottom: 0.35rem;
}

.note-content p {
    margin: 0;
    font-size: 0.88rem;
    line-height: 1.65;
    color: #1e3a8a;
}

/* ========================================================
   DIAGRAMS & WORKFLOW STUDIO STYLING
   ======================================================== */
.diagrams-intro {
    background: #ffffff;
    border-radius: 16px;
    padding: 1.75rem 2rem;
    border: 1px solid #e2e8f0;
    margin-bottom: 1.75rem;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.04);
}

.diagrams-intro-text h2 {
    margin: 0 0 0.5rem;
    font-size: 1.45rem;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.diagrams-intro-text p {
    margin: 0 0 1.25rem;
    color: #475569;
    font-size: 0.95rem;
    line-height: 1.65;
}

.diagram-selector-pills {
    display: flex;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.diag-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    font-size: 0.86rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.diag-pill:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
}

.diag-pill.active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
}

.diag-pill-badge {
    background: rgba(255, 255, 255, 0.25);
    color: inherit;
    font-size: 0.72rem;
    padding: 1px 6px;
    border-radius: 9999px;
}

.diag-pill:not(.active) .diag-pill-badge {
    background: #e2e8f0;
    color: #475569;
}

.diagram-workspace {
    display: flex;
    flex-direction: column;
    gap: 1.75rem;
}

.diagram-canvas-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.06);
    padding: 1.75rem;
    overflow: hidden;
}

.canvas-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 1.75rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.canvas-tag {
    font-size: 0.78rem;
    font-weight: 800;
    color: #2563eb;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.canvas-title {
    margin: 0.25rem 0 0.45rem;
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
}

.canvas-desc {
    margin: 0;
    font-size: 0.9rem;
    color: #64748b;
    line-height: 1.6;
}

.canvas-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.nodes-count {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: #475569;
    background: #f1f5f9;
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
}

/* Visual Flowchart Canvas */
.flowchart-container {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    overflow-x: auto;
    padding: 1rem 0.5rem 1.5rem;
    scrollbar-width: thin;
}

.flow-node-wrapper {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.flow-node-card {
    width: 230px;
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.15rem;
    cursor: pointer;
    transition: all 0.22s ease;
    user-select: none;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.04);
}

.flow-node-card:hover {
    border-color: #93c5fd;
    transform: translateY(-4px);
    box-shadow: 0 8px 18px -2px rgba(59, 130, 246, 0.15);
}

.flow-node-card.active {
    border-color: #2563eb;
    background: #eff6ff;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25), 0 8px 20px -2px rgba(37, 99, 235, 0.2);
}

.node-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.65rem;
}

.node-step-index {
    font-size: 0.75rem;
    font-weight: 900;
    color: #94a3b8;
    background: #f1f5f9;
    padding: 1px 6px;
    border-radius: 4px;
    font-family: monospace;
}

.node-doc-tag {
    font-size: 0.72rem;
    font-weight: 800;
    color: #1e3a8a;
    background: #e0f2fe;
    padding: 1px 6px;
    border-radius: 4px;
    font-family: monospace;
}

.node-main {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-bottom: 0.65rem;
}

.node-icon-box {
    width: 38px;
    height: 38px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.node-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}

.node-role {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 600;
    display: block;
}

.node-short-desc {
    margin: 0 0 0.85rem;
    font-size: 0.8rem;
    line-height: 1.5;
    color: #475569;
    height: 36px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.node-impact-pills {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.impact-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
}

.impact-pill.stock {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.impact-pill.journal {
    background: #faf5ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
}

/* Connector Arrow */
.flow-connector {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0 0.65rem;
    flex-shrink: 0;
}

.connector-line {
    width: 24px;
    height: 2px;
    background: #cbd5e1;
}

.connector-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 1px 6px;
    border-radius: 9999px;
    margin: 3px 0;
    white-space: nowrap;
}

.connector-arrow {
    font-size: 1.25rem;
    color: #3b82f6;
    font-weight: 900;
    line-height: 1;
}

/* Node Inspector Panel */
.node-inspector-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 6px 18px -2px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.inspector-header {
    border-top: 5px solid #2563eb;
    padding: 1.35rem 1.75rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.inspector-title-row {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.insp-icon {
    width: 46px;
    height: 46px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.insp-tag {
    font-size: 0.75rem;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
}

.insp-title {
    margin: 0.15rem 0 0.25rem;
    font-size: 1.25rem;
    font-weight: 900;
    color: #0f172a;
}

.insp-doc {
    font-size: 0.82rem;
    font-weight: 700;
    color: #2563eb;
    font-family: monospace;
}

.insp-screen-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: #2563eb;
    color: #ffffff;
    padding: 0.5rem 1rem;
    border-radius: 9px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 700;
    transition: all 0.2s ease;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
}

.insp-screen-btn:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
}

.inspector-body {
    padding: 1.75rem;
}

.insp-desc {
    font-size: 0.95rem;
    line-height: 1.7;
    color: #334155;
    margin: 0 0 1.5rem;
}

.insp-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.insp-detail-box {
    background: #f8fafc;
    border-radius: 10px;
    padding: 1rem 1.15rem;
    border: 1px solid #e2e8f0;
}

.insp-detail-box.has-effect {
    border-color: #bfdbfe;
    background: #f0f7ff;
}

.box-lbl {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 800;
    color: #475569;
    margin-bottom: 0.45rem;
}

.insp-detail-box code {
    font-family: monospace;
    font-size: 0.82rem;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 2px 7px;
    border-radius: 4px;
    color: #1e3a8a;
    display: inline-block;
}

.perm-badge {
    background: #f1f5f9;
    color: #0f172a;
    font-family: monospace;
    font-size: 0.8rem;
    padding: 2px 8px;
    border-radius: 5px;
    font-weight: 700;
}

.detail-val {
    font-size: 0.86rem;
    color: #1e293b;
    font-weight: 600;
    line-height: 1.5;
}

.journal-mini-lines {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    font-size: 0.82rem;
}

.j-mini-line {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
}

.j-side {
    font-weight: 800;
    font-size: 0.75rem;
    padding: 1px 5px;
    border-radius: 3px;
}

.j-mini-line.debit .j-side { background: #dbeafe; color: #1e40af; }
.j-mini-line.credit .j-side { background: #dcfce7; color: #166534; }
.j-acc { font-family: monospace; font-weight: 600; color: #0f172a; }

.insp-steps-box {
    background: #f8fafc;
    border-radius: 10px;
    padding: 1.15rem 1.35rem;
    border: 1px solid #e2e8f0;
}

.insp-steps-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.88rem;
    font-weight: 800;
    color: #0f766e;
    margin-bottom: 0.65rem;
}

.insp-steps-list {
    margin: 0;
    padding-right: 1.35rem;
    font-size: 0.88rem;
    line-height: 1.7;
    color: #334155;
}

.flow-footer-action {
    display: flex;
    justify-content: center;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
}

/* ========================================================
   TAB 2: SIMULATORS STYLING
   ======================================================== */
.simulators-intro, .rules-intro, .shortcuts-intro {
    background: #ffffff;
    border-radius: 16px;
    padding: 1.75rem 2rem;
    border: 1px solid #e2e8f0;
    margin-bottom: 1.75rem;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.04);
}

.simulators-intro h2, .rules-intro h2, .shortcuts-intro h2 {
    margin: 0 0 0.5rem;
    font-size: 1.45rem;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.simulators-intro p, .rules-intro p, .shortcuts-intro p {
    margin: 0;
    color: #475569;
    font-size: 0.95rem;
    line-height: 1.65;
}

.simulators-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.75rem;
}

.simulator-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 14px -2px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.sim-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.35rem 1.75rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.sim-icon {
    width: 44px;
    height: 44px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sim-card-header h3 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
}

.sim-subtitle {
    font-size: 0.82rem;
    color: #64748b;
}

.sim-card-body {
    padding: 1.75rem;
}

.sim-inputs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.sim-input-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.sim-input-item label {
    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
}

.sim-result-box {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1.35rem;
    border: 1px solid #e2e8f0;
}

.sim-kpi-row {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
}

.sim-kpi {
    flex: 1;
    min-width: 180px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 0.85rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.sim-kpi.highlight {
    background: #f0fdf4;
    border-color: #86efac;
}

.sim-kpi .kpi-label {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 600;
}

.sim-kpi .kpi-val {
    font-size: 1.35rem;
    font-weight: 900;
    color: #0f172a;
}

.sim-kpi .kpi-val.green {
    color: #15803d;
}

.stock-dist-bar-wrap {
    margin-bottom: 1.15rem;
}

.dist-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #475569;
    display: block;
    margin-bottom: 0.45rem;
}

.stock-dist-bar {
    height: 18px;
    border-radius: 9px;
    overflow: hidden;
    background: #e2e8f0;
    display: flex;
    margin-bottom: 0.5rem;
}

.bar-segment {
    height: 100%;
    transition: width 0.3s ease;
}

.bar-segment.sellable { background: #10b981; }
.bar-segment.reserved { background: #f59e0b; }
.bar-segment.damaged { background: #ef4444; }
.bar-segment.quarantined { background: #8b5cf6; }

.bar-legend {
    display: flex;
    gap: 1.25rem;
    flex-wrap: wrap;
    font-size: 0.78rem;
    color: #475569;
}

.leg-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.leg-item .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.leg-item .dot.sellable { background: #10b981; }
.leg-item .dot.reserved { background: #f59e0b; }
.leg-item .dot.damaged { background: #ef4444; }
.leg-item .dot.quarantined { background: #8b5cf6; }

.sim-formula-note {
    font-size: 0.82rem;
    color: #475569;
    margin-top: 0.85rem;
    padding-top: 0.85rem;
    border-top: 1px dashed #cbd5e1;
}

.sim-formula-note code {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 2px 7px;
    border-radius: 5px;
    color: #1e3a8a;
    font-family: monospace;
    font-size: 0.82rem;
    margin-right: 0.4rem;
}

/* FIFO Simulator specific */
.fifo-layers-list {
    margin-bottom: 1.25rem;
}

.fifo-layers-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 0.65rem;
}

.layer-rows {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.layer-row {
    flex: 1;
    min-width: 170px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.82rem;
}

.layer-badge {
    align-self: flex-start;
    background: #e0f2fe;
    color: #0369a1;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
}

.fifo-sell-input {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.35rem;
    flex-wrap: wrap;
}

.fifo-sell-input label {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0f172a;
}

.max-hint {
    font-size: 0.8rem;
    color: #64748b;
}

.fifo-output-box {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
}

.fifo-output-box h4 {
    margin: 0 0 0.85rem;
    font-size: 0.95rem;
    font-weight: 800;
    color: #166534;
}

.consumption-steps {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    margin-bottom: 1.15rem;
}

.c-step {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.86rem;
    color: #15803d;
}

.fifo-totals-row {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    padding-top: 1rem;
    border-top: 1px dashed #86efac;
}

.fifo-stat {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.84rem;
    color: #374151;
}

.fifo-stat strong {
    font-size: 1.15rem;
    color: #0f172a;
    font-family: monospace;
}

.fifo-stat strong.text-blue {
    color: #2563eb;
}

/* ROP Simulator */
.rop-summary {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.rop-num-box {
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 10px;
    padding: 1rem 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.rop-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: #92400e;
}

.rop-num {
    font-size: 1.75rem;
    font-weight: 900;
    color: #b45309;
}

.rop-advice {
    flex: 1;
    min-width: 250px;
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.65;
    color: #334155;
}

/* Double-entry simulator */
.op-selector-row {
    margin-bottom: 1.35rem;
}

.op-selector-row label {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e293b;
    display: block;
    margin-bottom: 0.5rem;
}

.journal-op-desc {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #f5f3ff;
    color: #6b21a8;
    padding: 0.65rem 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    font-size: 0.86rem;
    font-weight: 600;
    border: 1px solid #e9d5ff;
}

.journal-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}

.journal-table th, .journal-table td {
    padding: 0.75rem 1rem;
    border: 1px solid #e2e8f0;
}

.journal-table th {
    background: #f8fafc;
    color: #0f172a;
    font-weight: 800;
}

.credit-line td {
    background: #fafafa;
}

.text-right { text-align: left; }
[dir="rtl"] .text-right { text-align: left; }
.font-mono { font-family: monospace; font-size: 0.92rem; }
.text-muted { color: #64748b; font-size: 0.82rem; }
.text-green { color: #15803d; font-weight: 800; }

.totals-row td {
    background: #f1f5f9;
    font-weight: 800;
}

.balanced-badge {
    background: #dcfce7;
    color: #166534;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

/* ========================================================
   TAB 3: MASTER RULES STYLING
   ======================================================== */
.rules-categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
}

.rule-cat-card {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.rule-cat-head {
    border-top: 4px solid #2563eb;
    padding: 1.15rem 1.35rem;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.rule-cat-head h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
}

.rule-cat-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.master-rule-item {
    background: #f8fafc;
    border-radius: 10px;
    padding: 1.15rem;
    border: 1px solid #e2e8f0;
}

.rule-badge-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.rule-code-badge {
    background: #0f172a;
    color: #ffffff;
    padding: 1px 7px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 800;
    font-family: monospace;
}

.rule-enforcement {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
}

.rule-enforcement.critical {
    background: #fee2e2;
    color: #991b1b;
}

.rule-enforcement.warning {
    background: #fef3c7;
    color: #92400e;
}

.rule-name {
    margin: 0 0 0.45rem;
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
}

.rule-desc {
    margin: 0 0 0.65rem;
    font-size: 0.85rem;
    line-height: 1.6;
    color: #475569;
}

.rule-formula-box {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 0.4rem 0.65rem;
    margin-bottom: 0.5rem;
}

.rule-formula-box code {
    font-family: monospace;
    font-size: 0.8rem;
    color: #1e3a8a;
    word-break: break-all;
}

.rule-consequence {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    color: #b91c1c;
}

/* ========================================================
   TAB 4: SHORTCUTS STYLING
   ======================================================== */
.shortcuts-tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
}

.shortcut-block {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.block-head {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 1.15rem 1.35rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.block-head h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
}

.shortcuts-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.86rem;
}

.shortcuts-table th, .shortcuts-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
}

.shortcuts-table th {
    background: #ffffff;
    color: #475569;
    font-weight: 700;
    font-size: 0.8rem;
}

kbd {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.1);
    color: #1e293b;
    display: inline-block;
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1;
    padding: 3px 6px;
    white-space: nowrap;
}

/* ========================================================
   TAB 5: GLOSSARY & FAQ STYLING
   ======================================================== */
.glossary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
    gap: 1.35rem;
}

.glossary-box {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.35rem;
    border: 1px solid #e2e8f0;
}

.term-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 0.55rem;
}

.term-ar {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
}

.term-en {
    font-size: 0.78rem;
    color: #64748b;
    font-family: monospace;
}

.term-def {
    font-size: 0.88rem;
    line-height: 1.65;
    color: #475569;
    margin: 0 0 0.75rem;
}

.term-formula {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
    color: #1e3a8a;
}

.faq-list {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.faq-item {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.35rem;
    border: 1px solid #e2e8f0;
}

.faq-q {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 0.55rem;
}

.q-icon {
    color: #0891b2;
    font-size: 1.15rem;
}

.faq-a {
    font-size: 0.9rem;
    line-height: 1.75;
    color: #334155;
    padding-right: 1.85rem;
}

/* Empty State */
.empty-docs-state {
    text-align: center;
    padding: 4.5rem 2rem;
    background: #ffffff;
    border-radius: 16px;
    border: 2px dashed #cbd5e1;
    color: #64748b;
}

.empty-docs-state h3 {
    margin: 1rem 0 0.5rem;
    color: #1e293b;
    font-size: 1.25rem;
}

.empty-docs-state p {
    margin: 0 0 1.5rem;
    font-size: 0.92rem;
}

/* Back to Top Floating Button */
.back-to-top-btn {
    position: fixed;
    bottom: 2.25rem;
    left: 2.25rem;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: #0f172a;
    color: #ffffff;
    border: none;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.28);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.25rem;
    transition: all 0.22s ease;
    z-index: 100;
}

.back-to-top-btn:hover {
    background: #2563eb;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
}

/* Responsive Breakpoints */
@media (max-width: 992px) {
    .doc-body-wrapper {
        grid-template-columns: 1fr;
    }

    .doc-sidebar-nav {
        display: none;
    }

    .doc-hero-title {
        font-size: 1.65rem;
    }

    .flow-steps {
        flex-direction: column;
        align-items: stretch;
    }

    .flow-arrow {
        transform: rotate(90deg);
        align-self: center;
    }
}

/* Print Optimization */
@media print {
    .doc-hero,
    .doc-sidebar-nav,
    .doc-toolbar,
    .quick-tags-row,
    .role-pills-bar,
    .view-tabs-container,
    .header-actions,
    .back-to-top-btn {
        display: none !important;
    }

    .doc-body-wrapper {
        display: block !important;
    }

    .doc-section-card {
        border: 1px solid #94a3b8 !important;
        box-shadow: none !important;
        page-break-inside: avoid;
        margin-bottom: 2rem !important;
    }

    .section-card-body {
        display: block !important;
    }
}
</style>
