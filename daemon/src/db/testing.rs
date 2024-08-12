// Copyright 2017-2020 Aron Heinecke
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//   http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

//! Testing harness
use super::*;
pub use crate::*;
use chrono::naive::NaiveDate;
use chrono::{TimeZone, Utc};
// pub use mysql::{from_row, from_row_opt, Opts, OptsBuilder, Pool, PooledConn, Row, Transaction};
pub use super::prelude::*;
pub use mysql::from_row;
use regex;

// change these settings to connect to another DB
// Please note: the TEST_DB has to be empty (no tables)!
const TEST_USER: &str = "root";
const TEST_PASSWORD: &str = "root";
const TEST_DB: &str = "test";
const TEST_PORT: u16 = 3306;
const TEST_IP: &str = "127.0.0.1";
pub const REGEX_VIEW: &str = r"CREATE (OR REPLACE)? VIEW `([\-_a-zA-Z]+)` AS";

/// Cleanup guard for tests removing it's database content afterwards
pub struct CleanupGuard {
    pub pool: Pool,
    pub tables: Vec<String>,
    pub views: Vec<String>,
    pub disabled: bool,
}

impl CleanupGuard {
    pub fn new(pool: Pool) -> CleanupGuard {
        CleanupGuard {
            pool,
            tables: Vec::new(),
            views: Vec::new(),
            disabled: false,
        }
    }

    /// Add table to drop on cleanup
    pub fn add_table(&mut self, table: String) {
        self.tables.push(table);
    }
}

impl Drop for CleanupGuard {
    fn drop(&mut self) {
        if self.disabled {
            println!("CleanupGuard disabled!");
            return;
        }
        let mut conn = self.pool.get_conn().unwrap();
        for view in &self.views {
            conn.query_drop(format!("DROP VIEW IF EXISTS `{}`;", view))
                .unwrap();
            println!("dropped view {}", view);
        }
        while let Some(table) = self.tables.pop() {
            conn.query_drop(format!("DROP TABLE IF EXISTS `{}`;", table))
                .unwrap();
            println!("dropped table {}", table);
        }
    }
}

/// Setup db tables, does crash if tables are existing
/// Created as temporary if specified, using the u32 as name suffix: myTable{},u32
/// Returns all table names which got created
pub fn setup_tables(pool: Pool) -> Result<CleanupGuard> {
    let tables = get_db_create_sql();
    let reg_tables = regex::Regex::new(r"CREATE TABLE `([\-_a-zA-Z]+)` \(").unwrap();
    let reg_views = regex::Regex::new(REGEX_VIEW).unwrap();
    let mut guard = CleanupGuard::new(pool);
    let mut conn = guard.pool.get_conn()?;
    for a in tables {
        if let Some(cap) = reg_tables.captures(&a) {
            let table = cap[1].to_string();
            conn.query_drop(format!("DROP TABLE IF EXISTS `{}`;", &table))?;
            conn.query_drop(a)?;
            guard.tables.push(table);
        } else if let Some(cap) = reg_views.captures(&a) {
            let view = cap[2].to_string();
            conn.query_drop(format!("DROP VIEW IF EXISTS `{}`;", &view))?;
            conn.query_drop(a)?;
            guard.views.push(view);
        } else {
            return Err(Error::InvalidDBSetup(format!(
                "Expected table/view, found {}",
                a
            )));
        }
    }
    Ok(guard)
}

/// Verifies DB has not tables, panics otherwise
pub fn verify_empty_db(conn: &mut PooledConn) {
    let v: Vec<String> = conn.query_map("SHOW TABLES;", |r| r).unwrap();
    if !v.is_empty() {
        panic!("Found tables in test DB: {:?}", v);
    }
}

/// Helper method to connect to the db
pub fn connect_db() -> Pool {
    let password;
    let user = match option_env!("TEST_DB_USER") {
        Some(u) => {
            password = option_env!("TEST_DB_PW");
            u
        }
        None => {
            password = Some(TEST_PASSWORD);
            TEST_USER
        }
    };

    let opts: Opts = OptsBuilder::new()
        .ip_or_hostname(Some("foo"))
        .db_name(Some(TEST_DB))
        .user(Some(user))
        .pass(password)
        .tcp_port(TEST_PORT)
        .ip_or_hostname(Some(TEST_IP))
        .prefer_socket(false)
        .into();
    Pool::new_manual(POOL_MIN_CONN, POOL_MAX_CONN, opts).unwrap() // min 6, check_import_account_insert will deadlock otherwise
}

/// Helper method to setup a db connection
/// the returned connection has a complete temporary table setup
/// new connection retrieved from the pool can't interact with these
pub fn setup_db() -> (PooledConn, CleanupGuard) {
    let pool = connect_db();
    verify_empty_db(&mut pool.get_conn().unwrap());
    let guard = setup_tables(pool.clone()).unwrap();
    let conn = pool.get_conn().unwrap();
    (conn, guard)
}

/// Create member struct
pub fn create_member(name: &str, id: i32, exp: i64, contribution: i32) -> Member {
    Member {
        name: String::from(name),
        id,
        exp,
        contribution,
    }
}

/// Insert full membership with cause
pub fn insert_full_membership(
    conn: &mut PooledConn,
    id: &i32,
    join: &NaiveDate,
    leave: &NaiveDate,
    cause: &str,
    kicked: bool,
) -> i32 {
    let nr = insert_membership(conn, &id, join, Some(leave));
    insert_membership_cause(conn, &nr, cause, kicked);
    nr
}

/// Insert random membership for person, when from/to doesn't matter
pub fn insert_random_membership(conn: &mut PooledConn, id: i32, amount: usize) {
    // generate random entries and assure no from-duplicates exist
    let mut set = std::collections::HashSet::new();
    for _i in 0..amount {
        let mut retries = 0;
        const LIMIT: i64 = 100;
        let (from, to) = loop {
            let (from, to) = generate_random_datepair(retries);
            println!("{:?} {:?}", from, to);
            if set.insert(from.clone()) {
                break (from, to);
            }
            retries += 1;
            if retries > LIMIT {
                panic!(
                    "Reached limit generating random datepair {:?} {:?}! {}/{}",
                    from, to, retries, LIMIT
                );
            }
        };
        conn.exec_drop(
            "INSERT INTO `membership` (`id`,`from`,`to`) VALUES (?,?,?)",
            (id, &from, &to),
        )
        .unwrap();
    }
}

/// Generate pair of from-to membership random values
fn generate_random_datepair(offset: i64) -> (NaiveDate, Option<NaiveDate>) {
    let from: u16 = rand::random();
    let from = from as i64 + offset;
    let dist: u8 = rand::random();
    let to = if rand::random() {
        let to = from as i64 + (dist as i64);
        Some(to)
    } else {
        None
    };
    let date = Utc.timestamp_opt(0, 0).unwrap().date_naive();
    let to = to.map(|v| date.clone().checked_add_signed(Duration::days(v)).unwrap());
    let from = date.checked_add_signed(Duration::days(from)).unwrap();
    (from, to)
}

/// Insert membership, return nr on success
pub fn insert_membership(
    conn: &mut PooledConn,
    id: &i32,
    join: &NaiveDate,
    leave: Option<&NaiveDate>,
) -> i32 {
    conn.exec_drop(
        "INSERT INTO `membership` (`id`,`from`,`to`) VALUES (?,?,?)",
        (id, join, leave),
    )
    .unwrap();
    let nr = conn.last_insert_id();
    assert_ne!(0, nr, "no last insertion ID!");
    nr as i32
}

/// Insert open trial for id
pub fn insert_trial(conn: &mut PooledConn, id: &i32, start: &NaiveDate) {
    conn.exec_drop(
        "INSERT INTO `member_trial` (`id`,`from`,`to`) VALUES (?,?,NULL)",
        (id, start),
    )
    .unwrap();
}

/// Insert membership cause
pub fn insert_membership_cause(conn: &mut PooledConn, nr: &i32, cause: &str, kicked: bool) {
    conn.exec_drop(
        "INSERT INTO `membership_cause` (`nr`,`kicked`,`cause`) VALUES (?,?,?)",
        (nr, kicked, cause),
    )
    .unwrap();
}

/// insert key-value entry into settings table
pub fn insert_settings(conn: &mut PooledConn, key: &str, value: &str) {
    conn.exec_drop(
        "INSERT INTO `settings` (`key`,`value`) VALUES(?,?)",
        (key, value),
    )
    .unwrap();
}

#[cfg(test)]
mod test {
    use super::*;

    /// Test connection
    #[test]
    fn connection_test() {
        connect_db();
    }

    /// Test table setup
    #[test]
    fn setup_tables_test() {
        let (_conn, _guard) = setup_db();
    }

    /// Verify REGEX_VIEW and also behavior of regex captures
    #[test]
    fn test_view_regex() {
        let reg = regex::Regex::new(REGEX_VIEW).unwrap();

        {
            let test_1 = "CREATE OR REPLACE VIEW `ranked` AS";
            let cap_1 = reg.captures(test_1).unwrap();
            assert_eq!("ranked", &cap_1[2]);
        }
        {
            let test_2 = "CREATE OR REPLACE VIEW `ranked` AS";
            let cap_2 = reg.captures(test_2).unwrap();
            assert_eq!("ranked", &cap_2[2]);
        }
    }
}
