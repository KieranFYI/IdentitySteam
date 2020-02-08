/**
 * vim: set ts=4 :
 * =============================================================================
 * SourceMod SQL Admins Plugin (Threaded)
 * Fetches admins from an SQL database dynamically.
 *
 * SourceMod (C)2004-2008 AlliedModders LLC.  All rights reserved.
 * =============================================================================
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, version 3.0, as published by the
 * Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * As a special exception, AlliedModders LLC gives you permission to link the
 * code of this program (as well as its derivative works) to "Half-Life 2," the
 * "Source Engine," the "SourcePawn JIT," and any Game MODs that run on software
 * by the Valve Corporation.  You must obey the GNU General Public License in
 * all respects for all other code used.  Additionally, AlliedModders LLC grants
 * this exception to all derivative works.  AlliedModders LLC defines further
 * exceptions, found in LICENSE.txt (as of this writing, version JULY-31-2007),
 * or <http://www.sourcemod.net/license.php>.
 *
 * Version: $Id$
 */
 
/* We like semicolons */
#pragma semicolon 1
#pragma newdecls required

#include <sourcemod>
#include <sdktools>
#include <chat>

public Plugin myinfo = 
{
	name = "Identity Steam",
	author = "Kieran",
	description = "Reads admins from SQL dynamically",
	version = SOURCEMOD_VERSION,
	url = "http://www.sourcemod.net/"
};

Database hDatabase = null;						/** Database connection */

enum struct PlayerInfo {
	bool authed; /** Whether a player has been "pre-authed" */
	char tag[255];
}

PlayerInfo playerinfo[MAXPLAYERS+1];

//#define _DEBUG

public void OnMapEnd()
{
	/**
	 * Clean up on map end just so we can start a fresh connection when we need it later.
	 */
	delete hDatabase;
}

public void OnPluginStart()
{
	HookEvent("player_changename", OnNameChange, EventHookMode_Pre);
}

public bool OnClientConnect(int client, char[] rejectmsg, int maxlen)
{
	playerinfo[client].authed = false;
	playerinfo[client].tag = "";
	return true;
}

public void OnClientDisconnect(int client)
{
	playerinfo[client].authed = false;
	playerinfo[client].tag = "";
}

public void OnDatabaseConnect(Database db, const char[] error, any data)
{
#if defined _DEBUG
	PrintToServer("OnDatabaseConnect(%x, %d)", db, data);
#endif

	/**
		* If this happens to be an old connection request, ignore it.
		*/
	if (hDatabase != null)
	{
		delete db;
		return;
	}

	hDatabase = db;

	/**
		* See if the connection is valid.  If not, don't un-mark the caches
		* as needing rebuilding, in case the next connection request works.
	*/
	if (hDatabase == null)
	{
		LogError("Failed to connect to database: %s", error);
		return;
	}

	FetchUsersWeCan(hDatabase);
}

void RequestDatabaseConnection()
{
	Database.Connect(OnDatabaseConnect, "forums");
}

public void OnRebuildAdminCache(AdminCachePart part)
{

	/**
	* If we don't have a database connection, we can't do any lookups just yet.
	*/
	if (!hDatabase)
	{
		RequestDatabaseConnection();
		return;
	}

	FetchUsersWeCan(hDatabase);
}

public Action OnClientPreAdminCheck(int client)
{
	playerinfo[client].authed = true;

	/**
	 * Play nice with other plugins.  If there's no database, don't delay the 
	 * connection process.  Unfortunately, we can't attempt anything else and 
	 * we just have to hope either the database is waiting or someone will type 
	 * sm_reloadadmins.
	 */
	if (hDatabase == null)
	{
		return Plugin_Continue;
	}

	/**
	 * If someone has already assigned an admin ID (bad bad bad), don't 
	 * bother waiting.
	 */
	if (GetUserAdmin(client) != INVALID_ADMIN_ID)
	{
		return Plugin_Continue;
	}

	FetchUser(hDatabase, client);

	return Plugin_Handled;
}

public void OnReceiveUser(Database db, DBResultSet rs, const char[] error, any data)
{
	DataPack pk = view_as<DataPack>(data);
	pk.Reset();

	int client = pk.ReadCell();
	
	
	/**
	 * If we need to use the results, make sure they succeeded.
	 */
	if (rs == null)
	{
		char query[255];
		pk.ReadString(query, sizeof(query));
		LogError("SQL error receiving user: %s", error);
		LogError("Query dump: %s", query);
		RunAdminCacheChecks(client);
		NotifyPostAdminCheck(client);
		delete pk;
		return;
	}
	
	int num_accounts = rs.RowCount;
	if (num_accounts == 0)
	{
		RunAdminCacheChecks(client);
		NotifyPostAdminCheck(client);
		delete pk;
		return;
	}

	if (!rs.FetchRow()) {
		return;
	}
#if defined _DEBUG
	int id = rs.FetchInt(0);
#endif
	char identity[80];
	rs.FetchString(1, identity, sizeof(identity));
	char flags[32];
	rs.FetchString(2, flags, sizeof(flags));
	char name[80];
	rs.FetchString(3, name, sizeof(name));
	int immunity = rs.FetchInt(4);
	rs.FetchString(5, playerinfo[client].tag, 255);
	
	AdminId adm;
	/* For dynamic admins we clear anything already in the cache. */
	if ((adm = FindAdminByIdentity(AUTHMETHOD_STEAM, identity)) != INVALID_ADMIN_ID)
	{
		RemoveAdmin(adm);
	}
	
	adm = CreateAdmin(name);
	if (!adm.BindIdentity(AUTHMETHOD_STEAM, identity))
	{
		LogError("Could not bind prefetched SQL admin (identity \"%s\")", identity);
		return;
	}
		
#if defined _DEBUG
	PrintToServer("Found SQL admin (%d,%s,%s,%s,%d):%d", id, identity, flags, name, immunity, adm, user_lookup[total_users-1]);
#endif

	adm.ImmunityLevel = immunity;
	
	/* Apply each flag */
	int len = strlen(flags);
	AdminFlag flag;
	for (int i=0; i<len; i++)
	{
		if (!FindFlagByChar(flags[i], flag))
		{
			return;
		}
		adm.SetFlag(flag, true);
	}
	
	/**
	 * Try binding the user.
	 */	
	RunAdminCacheChecks(client);
	
	NotifyPostAdminCheck(client);
	delete pk;
}

void FetchUser(Database db, int client)
{

	char admin_id[64];
	if (!GetClientAuthId(client, AuthId_SteamID64, admin_id, sizeof(admin_id))) {
		return;
	}

	/**
	* Construct the query using the information the user gave us.
	*/
	char query[512];
	SQL_FormatQuery(db, query, sizeof(query),  "SELECT user_id, identity, flags, name, immunity, chat_rank FROM xf_kieran_identitysteam_users WHERE identity = %s", admin_id);

	/**
	* Send the actual query.
	*/	
	DataPack pk = new DataPack();
	pk.WriteCell(client);
	pk.WriteString(query);

#if defined _DEBUG
	PrintToServer("Sending user query: %s", query);
#endif
	
	db.Query(OnReceiveUser, query, pk, DBPrio_High);
}

void FetchUsersWeCan(Database db)
{
	for (int i=1; i<=MaxClients; i++)
	{
		if (playerinfo[i].authed && GetUserAdmin(i) == INVALID_ADMIN_ID)
		{
			FetchUser(db, i);
		}
	}
}

public void OnClientPostAdminCheck(int client)
{
	AdminId admin = GetUserAdmin(client);

	if (admin == INVALID_ADMIN_ID)
	{
		return;
	}
	
	char username[80];

	if (GetAdminUsername(admin, username, sizeof(username)) > 0) {
		SetClientName(client, username);
	}
}

public Action OnNameChange(Handle event, const char[] name, bool dontBroadcast)
{

	int client = GetClientOfUserId(GetEventInt(event, "userid"));
	AdminId admin = GetUserAdmin(client);

	if (admin == INVALID_ADMIN_ID)
	{
		return Plugin_Continue;
	}
	
	char username[80];

	if (GetAdminUsername(admin, username, sizeof(username)) > 0) {
		SetEventBroadcast(event, true);

		char newname[32];
		GetEventString(event, "newname", newname, sizeof(newname));

		if (!StrEqual(username, newname)) {
			PrintToServer("[%s] [%s]", username, newname);
			SetClientName(client, username);
			return Plugin_Stop;
		}
	}

	return Plugin_Continue;
}

public void Chat_OnChatMessage(int client, char[] name, char[] message)
{
	Format(name, 400, "{default}%s%s", playerinfo[client].tag, name);
}