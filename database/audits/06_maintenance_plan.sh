#!/bin/bash
# ============================================
# Database Maintenance Plan Script
# ============================================
# Purpose: Automated maintenance tasks for ERP database
# Schedule: Run via cron (daily, weekly, monthly)
# Usage: ./maintenance_plan.sh [daily|weekly|monthly]
# ============================================

DB_NAME="your_database_name"
DB_USER="your_username"
DB_PASS="your_password"
DB_HOST="localhost"
BACKUP_DIR="/var/backups/mysql"
LOG_DIR="/var/log/mysql/maintenance"
DATE=$(date +%Y%m%d_%H%M%S)

# Create directories if they don't exist
mkdir -p $BACKUP_DIR
mkdir -p $LOG_DIR

LOG_FILE="$LOG_DIR/maintenance_$DATE.log"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a $LOG_FILE
}

# ============================================
# Daily Maintenance Tasks
# ============================================
daily_tasks() {
    log "=== Starting Daily Maintenance Tasks ==="
    
    # 1. Check disk space
    log "Checking disk space..."
    df -h /var/lib/mysql >> $LOG_FILE
    
    # 2. Check MySQL process list
    log "Checking MySQL process list..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "SHOW PROCESSLIST;" >> $LOG_FILE
    
    # 3. Check for long-running queries
    log "Checking for long-running queries..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "SELECT * FROM information_schema.processlist WHERE time > 300;" >> $LOG_FILE
    
    # 4. Check for locked tables
    log "Checking for locked tables..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "SHOW OPEN TABLES WHERE In_use > 0;" >> $LOG_FILE
    
    # 5. Check InnoDB status
    log "Checking InnoDB status..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "SHOW ENGINE INNODB STATUS\G" >> $LOG_FILE
    
    # 6. Check slow queries
    log "Checking slow query log size..."
    if [ -f /var/log/mysql/slow-query.log ]; then
        ls -lh /var/log/mysql/slow-query.log >> $LOG_FILE
    fi
    
    # 7. Run data integrity check (sample)
    log "Running data integrity checks..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < database/audits/04_data_integrity_checks.sql >> $LOG_FILE 2>&1
    
    log "=== Daily Maintenance Tasks Completed ==="
}

# ============================================
# Weekly Maintenance Tasks
# ============================================
weekly_tasks() {
    log "=== Starting Weekly Maintenance Tasks ==="
    
    # 1. Analyze tables to update statistics
    log "Analyzing tables..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME -e "
        ANALYZE TABLE products;
        ANALYZE TABLE warehouses;
        ANALYZE TABLE warehouse_inventory;
        ANALYZE TABLE product_warehouse_assignments;
        ANALYZE TABLE bin_assignments;
        ANALYZE TABLE product_components;
        ANALYZE TABLE stock_movements;
    " >> $LOG_FILE
    
    # 2. Check index usage
    log "Checking index usage..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "
        SELECT object_schema, object_name, index_name, count_star
        FROM performance_schema.table_io_waits_summary_by_index_usage
        WHERE object_schema = '$DB_NAME'
        ORDER BY count_star ASC;
    " >> $LOG_FILE
    
    # 3. Check table sizes
    log "Checking table sizes..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "
        SELECT 
            table_name,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.tables
        WHERE table_schema = '$DB_NAME'
        ORDER BY (data_length + index_length) DESC;
    " >> $LOG_FILE
    
    # 4. Check for fragmentation
    log "Checking for fragmentation..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "
        SELECT 
            table_name,
            ROUND(data_free / 1024 / 1024, 2) AS free_mb
        FROM information_schema.tables
        WHERE table_schema = '$DB_NAME'
        AND data_free > 0
        ORDER BY data_free DESC;
    " >> $LOG_FILE
    
    # 5. Review error log
    log "Reviewing error log..."
    tail -100 /var/log/mysql/error.log >> $LOG_FILE
    
    log "=== Weekly Maintenance Tasks Completed ==="
}

# ============================================
# Monthly Maintenance Tasks
# ============================================
monthly_tasks() {
    log "=== Starting Monthly Maintenance Tasks ==="
    
    # 1. Optimize fragmented tables
    log "Optimizing fragmented tables..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME -e "
        SELECT CONCAT('OPTIMIZE TABLE ', table_name, ';') AS optimize_command
        FROM information_schema.tables
        WHERE table_schema = '$DB_NAME'
        AND (data_free / (data_length + index_length)) > 0.1;
    " >> $LOG_FILE
    
    # Execute optimization for key tables
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME -e "
        OPTIMIZE TABLE warehouse_inventory;
        OPTIMIZE TABLE stock_movements;
        OPTIMIZE TABLE product_warehouse_assignments;
    " >> $LOG_FILE
    
    # 2. Full database backup
    log "Creating full database backup..."
    mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        $DB_NAME | gzip > $BACKUP_DIR/${DB_NAME}_full_$DATE.sql.gz
    
    log "Backup created: $BACKUP_DIR/${DB_NAME}_full_$DATE.sql.gz"
    
    # 3. Clean old backups (keep last 30 days)
    log "Cleaning old backups..."
    find $BACKUP_DIR -name "${DB_NAME}_full_*.sql.gz" -mtime +30 -delete
    
    # 4. Check foreign key integrity
    log "Checking foreign key integrity..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < database/audits/02_check_foreign_keys.sql >> $LOG_FILE 2>&1
    
    # 5. Review and update statistics
    log "Updating table statistics..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME -e "
        UPDATE information_schema.tables
        SET table_rows = NULL
        WHERE table_schema = '$DB_NAME';
    " >> $LOG_FILE
    
    # 6. Check for unused indexes
    log "Checking for unused indexes..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "
        SELECT 
            object_schema,
            object_name,
            index_name,
            count_star
        FROM performance_schema.table_io_waits_summary_by_index_usage
        WHERE object_schema = '$DB_NAME'
        AND index_name != 'PRIMARY'
        AND count_star = 0;
    " >> $LOG_FILE
    
    # 7. Performance review
    log "Performance review..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "
        SHOW GLOBAL STATUS LIKE 'Innodb%';
        SHOW GLOBAL STATUS LIKE 'Handler%';
        SHOW GLOBAL STATUS LIKE 'Select%';
    " >> $LOG_FILE
    
    log "=== Monthly Maintenance Tasks Completed ==="
}

# ============================================
# Quarterly Maintenance Tasks
# ============================================
quarterly_tasks() {
    log "=== Starting Quarterly Maintenance Tasks ==="
    
    # 1. Review and adjust MySQL configuration
    log "Reviewing MySQL configuration..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "SHOW VARIABLES;" >> $LOG_FILE
    
    # 2. Review storage capacity
    log "Reviewing storage capacity..."
    df -h >> $LOG_FILE
    du -sh /var/lib/mysql >> $LOG_FILE
    
    # 3. Review backup strategy
    log "Reviewing backup strategy..."
    ls -lh $BACKUP_DIR >> $LOG_FILE
    
    # 4. Security audit
    log "Running security audit..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "
        SELECT user, host FROM mysql.user;
        SHOW GRANTS;
    " >> $LOG_FILE
    
    # 5. Performance baseline
    log "Creating performance baseline..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "
        SHOW GLOBAL STATUS;
        SHOW GLOBAL VARIABLES;
    " > $BACKUP_DIR/performance_baseline_$DATE.txt
    
    log "=== Quarterly Maintenance Tasks Completed ==="
}

# ============================================
# Emergency Tasks
# ============================================
emergency_tasks() {
    log "=== Starting Emergency Tasks ==="
    
    # 1. Immediate backup
    log "Creating emergency backup..."
    mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS \
        --single-transaction \
        --quick \
        $DB_NAME | gzip > $BACKUP_DIR/${DB_NAME}_emergency_$DATE.sql.gz
    
    # 2. Check for deadlocks
    log "Checking for deadlocks..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "SHOW ENGINE INNODB STATUS\G" | grep -A 10 "LATEST DETECTED DEADLOCK" >> $LOG_FILE
    
    # 3. Check for long transactions
    log "Checking for long transactions..."
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "SHOW PROCESSLIST;" | grep -v "Sleep" >> $LOG_FILE
    
    log "=== Emergency Tasks Completed ==="
}

# ============================================
# Main Script Logic
# ============================================

case "$1" in
    daily)
        daily_tasks
        ;;
    weekly)
        daily_tasks
        weekly_tasks
        ;;
    monthly)
        daily_tasks
        weekly_tasks
        monthly_tasks
        ;;
    quarterly)
        daily_tasks
        weekly_tasks
        monthly_tasks
        quarterly_tasks
        ;;
    emergency)
        emergency_tasks
        ;;
    *)
        echo "Usage: $0 {daily|weekly|monthly|quarterly|emergency}"
        echo ""
        echo "Schedule recommendations:"
        echo "  Daily:   0 2 * * * /path/to/maintenance_plan.sh daily"
        echo "  Weekly:  0 3 * * 0 /path/to/maintenance_plan.sh weekly"
        echo "  Monthly: 0 4 1 * * /path/to/maintenance_plan.sh monthly"
        echo "  Quarterly: Manual execution"
        exit 1
        ;;
esac

log "=== Maintenance Plan Execution Completed ==="
exit 0
